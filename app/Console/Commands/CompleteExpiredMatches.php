<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteExpiredMatches extends Command
{
    protected $signature = 'pertandingan:selesaikan-kedaluwarsa';

    protected $description = 'Mengubah status pertandingan Berlangsung menjadi Selesai setelah waktu selesai terlewati';

    public function handle(): int
    {
        $now = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d H:i:s');

        $jumlah = DB::table('pertandingan')
            ->where('status', 'Berlangsung')
            ->whereNotNull('waktu_selesai')
            ->where('waktu_selesai', '<=', $now)
            ->update([
                'status' => 'Selesai',
                'updated_at' => $now,
            ]);

        $this->info("{$jumlah} pertandingan diperbarui menjadi Selesai.");

        return self::SUCCESS;
    }
}
