<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchedulerWebhookController extends Controller
{
    public const LOCK_KEY = 'pad_internal_scheduler_run';

    private const LOCK_ACQUIRED = 'acquired';
    private const LOCK_BUSY = 'busy';
    private const LOCK_ERROR = 'error';
    private const LOCK_NOT_APPLICABLE = 'not_applicable';

    /**
     * POST /api/internal/scheduler/run
     *
     * Menjalankan `php artisan schedule:run` melalui HTTP Cron yang dilindungi
     * header secret `X-Scheduler-Token` serta proteksi lock berlapis (Cache::lock + MySQL GET_LOCK).
     */
    public function run(Request $request): JsonResponse
    {
        $configuredToken = trim((string) config('services.scheduler.token', ''));

        if ($configuredToken === '') {
            Log::warning('Internal scheduler webhook rejected because SCHEDULER_CRON_TOKEN is not configured.');

            return response()->json([
                'success' => false,
                'message' => 'Scheduler belum dikonfigurasi.',
            ], 503);
        }

        $providedToken = (string) $request->header('X-Scheduler-Token', '');

        if ($providedToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Header X-Scheduler-Token wajib diisi.',
            ], 401);
        }

        if (!hash_equals($configuredToken, $providedToken)) {
            Log::warning('Internal scheduler webhook rejected due to invalid token.');

            return response()->json([
                'success' => false,
                'message' => 'Token scheduler tidak valid.',
            ], 403);
        }

        $lockTtl = max(30, (int) config('services.scheduler.lock_ttl', 240));
        $cacheLock = Cache::lock(self::LOCK_KEY, $lockTtl);

        if (!$cacheLock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduler sedang berjalan. Permintaan bersamaan dilewati.',
            ], 409);
        }

        $dbConnection = null;
        $lockPdo = null;
        $mysqlLockAcquired = false;

        try {
            // Distributed lock di MySQL/MariaDB bersama (fail-closed jika error).
            $mysqlLock = $this->acquireMysqlLock();

            if ($mysqlLock['status'] === self::LOCK_BUSY) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheduler sedang berjalan di instance lain. Permintaan dilewati.',
                ], 409);
            }

            if ($mysqlLock['status'] === self::LOCK_ERROR) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperoleh distributed lock database. Scheduler tidak dijalankan.',
                ], 503);
            }

            if ($mysqlLock['status'] === self::LOCK_ACQUIRED) {
                $mysqlLockAcquired = true;
                $dbConnection = $mysqlLock['connection'];
                $lockPdo = $mysqlLock['pdo'];
            }

            $exitCode = Artisan::call('schedule:run');

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0
                    ? 'Scheduler berhasil dijalankan.'
                    : 'Scheduler selesai dengan kode keluar non-nol.',
                'data' => [
                    'exit_code' => $exitCode,
                    'executed_at' => now('Asia/Jakarta')->toIso8601String(),
                ],
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $exception) {
            Log::error('Internal scheduler webhook encountered an unexpected error.', [
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menjalankan scheduler.',
            ], 500);
        } finally {
            if ($mysqlLockAcquired && $dbConnection !== null) {
                $this->releaseMysqlLock($dbConnection, $lockPdo);
            }

            try {
                $cacheLock->release();
            } catch (Throwable) {
                // Abaikan jika cache lock sudah kadaluarsa.
            }
        }
    }

    /**
     * Mengambil named lock MySQL/MariaDB secara fail-closed dan menyimpan referensi PDO
     * agar RELEASE_LOCK hanya dipanggil pada sesi PDO yang sama.
     *
     * @return array{status: string, connection: ?ConnectionInterface, pdo: mixed}
     */
    private function acquireMysqlLock(): array
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if (!in_array($driver, ['mysql', 'mariadb'], true)) {
                return ['status' => self::LOCK_NOT_APPLICABLE, 'connection' => null, 'pdo' => null];
            }

            $row = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_KEY]);
            $acquired = is_object($row) ? ($row->acquired ?? null) : null;

            if ($acquired === null) {
                Log::error('MySQL GET_LOCK returned NULL; failing closed to protect scheduler.');

                return ['status' => self::LOCK_ERROR, 'connection' => null, 'pdo' => null];
            }

            if ((int) $acquired === 1) {
                // Simpan objek PDO aktual setelah GET_LOCK berhasil untuk memastikan
                // RELEASE_LOCK berjalan di koneksi PDO yang sama.
                $pdo = method_exists($connection, 'getPdo') ? $connection->getPdo() : null;

                return ['status' => self::LOCK_ACQUIRED, 'connection' => $connection, 'pdo' => $pdo];
            }

            return ['status' => self::LOCK_BUSY, 'connection' => null, 'pdo' => null];
        } catch (Throwable $exception) {
            Log::error('Failed to acquire MySQL distributed lock for scheduler; failing closed.', [
                'exception' => $exception::class,
            ]);

            return ['status' => self::LOCK_ERROR, 'connection' => null, 'pdo' => null];
        }
    }

    /**
     * Melepas named lock MySQL hanya jika koneksi PDO masih sama dengan saat GET_LOCK.
     * Jika Laravel melakukan reconnect otomatis (objek PDO berbeda), sesi lama sudah terputus
     * sehingga MySQL telah otomatis melepas lock lama; koneksi baru tidak boleh memanggil RELEASE_LOCK.
     */
    private function releaseMysqlLock(ConnectionInterface $connection, mixed $lockPdo): void
    {
        try {
            if ($lockPdo !== null && method_exists($connection, 'getPdo') && $connection->getPdo() !== $lockPdo) {
                Log::warning('Database connection reconnected during scheduler execution; skipping RELEASE_LOCK on new PDO session.');

                return;
            }

            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_KEY]);
        } catch (Throwable $exception) {
            Log::warning('Failed to explicitly release MySQL distributed lock.', [
                'exception' => $exception::class,
            ]);
        }
    }
}
