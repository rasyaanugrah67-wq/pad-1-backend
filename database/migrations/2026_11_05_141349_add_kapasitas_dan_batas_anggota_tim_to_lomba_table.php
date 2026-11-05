<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->unsignedInteger('kapasitas_peserta')
                ->nullable()
                ->after('jenis_pendaftaran');

            $table->unsignedInteger('batas_anggota_tim')
                ->nullable()
                ->after('kapasitas_peserta');
        });
    }

    public function down(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->dropColumn([
                'kapasitas_peserta',
                'batas_anggota_tim',
            ]);
        });
    }
};