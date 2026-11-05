<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE warga
            MODIFY nik VARCHAR(20) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE warga
            MODIFY nik VARCHAR(20) NULL
        ");
    }
};