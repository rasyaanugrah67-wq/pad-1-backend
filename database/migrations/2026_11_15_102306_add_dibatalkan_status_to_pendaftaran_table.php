<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pendaftaran
            MODIFY status ENUM(
                'Menunggu',
                'Diterima',
                'Ditolak',
                'Dibatalkan'
            ) NOT NULL DEFAULT 'Menunggu'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pendaftaran
            MODIFY status ENUM(
                'Menunggu',
                'Diterima',
                'Ditolak'
            ) NOT NULL DEFAULT 'Menunggu'
        ");
    }
};
