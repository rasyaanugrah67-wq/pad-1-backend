<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SchedulerWebhookController;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class SchedulerWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::lock(SchedulerWebhookController::LOCK_KEY, 60)->forceRelease();
    }

    protected function tearDown(): void
    {
        Cache::lock(SchedulerWebhookController::LOCK_KEY, 60)->forceRelease();

        parent::tearDown();
    }

    public function test_request_rejected_when_scheduler_token_is_not_configured(): void
    {
        Config::set('services.scheduler.token', '');

        $response = $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'any-token',
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Scheduler belum dikonfigurasi.',
            ]);
    }

    public function test_request_rejected_when_token_header_is_missing(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $response = $this->postJson('/api/internal/scheduler/run');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Header X-Scheduler-Token wajib diisi.',
            ]);
        $this->assertStringNotContainsString('super-secret-cron-token-123', (string) $response->getContent());
    }

    public function test_request_rejected_when_token_is_invalid(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $response = $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'wrong-token',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Token scheduler tidak valid.',
            ]);
        $this->assertStringNotContainsString('super-secret-cron-token-123', (string) $response->getContent());
        $this->assertStringNotContainsString('wrong-token', (string) $response->getContent());
    }

    public function test_valid_token_with_mysql_get_lock_success_runs_scheduler_releases_lock_and_omits_raw_output(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $fakePdo = new stdClass();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('getPdo')->twice()->andReturn($fakePdo);
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT RELEASE_LOCK(?) AS released', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['released' => 1]);

        DB::shouldReceive('connection')->once()->andReturn($connection);

        Artisan::shouldReceive('call')
            ->once()
            ->with('schedule:run')
            ->andReturn(0);

        $response = $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'super-secret-cron-token-123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.exit_code', 0)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['exit_code', 'executed_at'],
            ])
            ->assertJsonMissingPath('data.output');

        $this->assertStringNotContainsString('super-secret-cron-token-123', (string) $response->getContent());

        // Pastikan Cache::lock juga sudah dilepas setelah eksekusi selesai.
        $lock = Cache::lock(SchedulerWebhookController::LOCK_KEY, 60);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_mysql_get_lock_returns_zero_blocks_execution_with_409(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['acquired' => 0]);
        $connection->shouldNotReceive('selectOne')
            ->with('SELECT RELEASE_LOCK(?) AS released', Mockery::any());

        DB::shouldReceive('connection')->once()->andReturn($connection);
        Artisan::shouldReceive('call')->never();

        $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'super-secret-cron-token-123',
        ])->assertStatus(409);
    }

    public function test_mysql_get_lock_returns_null_fails_closed_with_503_without_running_scheduler(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['acquired' => null]);

        DB::shouldReceive('connection')->once()->andReturn($connection);
        Artisan::shouldReceive('call')->never();

        $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'super-secret-cron-token-123',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_mysql_get_lock_database_exception_fails_closed_with_503_without_running_scheduler(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [SchedulerWebhookController::LOCK_KEY])
            ->andThrow(new RuntimeException('MySQL connection lost'));

        DB::shouldReceive('connection')->once()->andReturn($connection);
        Artisan::shouldReceive('call')->never();

        $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'super-secret-cron-token-123',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_locks_are_released_even_when_artisan_throws_exception(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $fakePdo = new stdClass();
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, 0) AS acquired', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['acquired' => 1]);
        $connection->shouldReceive('getPdo')->twice()->andReturn($fakePdo);
        $connection->shouldReceive('selectOne')
            ->once()
            ->with('SELECT RELEASE_LOCK(?) AS released', [SchedulerWebhookController::LOCK_KEY])
            ->andReturn((object) ['released' => 1]);

        DB::shouldReceive('connection')->once()->andReturn($connection);

        Artisan::shouldReceive('call')
            ->once()
            ->with('schedule:run')
            ->andThrow(new RuntimeException('Scheduler command crashed'));

        $this->postJson('/api/internal/scheduler/run', [], [
            'X-Scheduler-Token' => 'super-secret-cron-token-123',
        ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        // Pastikan Cache::lock tetap dilepas di blok finally meskipun Artisan melempar exception.
        $lock = Cache::lock(SchedulerWebhookController::LOCK_KEY, 60);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    /**
     * Menguji proteksi lapisan Cache::lock() terhadap request bersamaan pada store cache
     * (bukan simulasi dua koneksi MySQL sesungguhnya; skenario lock MySQL antar-instance
     * diuji terpisah pada test_mysql_get_lock_returns_zero_blocks_execution_with_409).
     */
    public function test_concurrent_scheduler_request_is_blocked_by_cache_lock_with_409_conflict(): void
    {
        Config::set('services.scheduler.token', 'super-secret-cron-token-123');

        $activeLock = Cache::lock(SchedulerWebhookController::LOCK_KEY, 60);
        $this->assertTrue($activeLock->get());

        try {
            $response = $this->postJson('/api/internal/scheduler/run', [], [
                'X-Scheduler-Token' => 'super-secret-cron-token-123',
            ]);

            $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Scheduler sedang berjalan. Permintaan bersamaan dilewati.',
                ]);
        } finally {
            $activeLock->release();
        }
    }
}
