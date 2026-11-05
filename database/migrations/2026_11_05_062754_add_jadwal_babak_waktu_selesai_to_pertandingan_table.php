<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertandingan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_jadwal')
                ->nullable()
                ->after('id_lomba');

            $table->string('babak', 50)
                ->nullable()
                ->after('peserta_2');

            $table->dateTime('waktu_selesai')
                ->nullable()
                ->after('waktu_mulai');

            $table->foreign('id_jadwal')
                ->references('id_jadwal')
                ->on('jadwal_lomba')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pertandingan', function (Blueprint $table) {
            $table->dropForeign(['id_jadwal']);

            $table->dropColumn([
                'id_jadwal',
                'babak',
                'waktu_selesai',
            ]);
        });
    }
};