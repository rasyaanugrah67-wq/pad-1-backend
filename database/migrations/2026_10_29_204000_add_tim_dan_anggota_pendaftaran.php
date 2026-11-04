<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah kolom nama_tim pada tabel pendaftaran (nullable untuk pendaftaran individu)
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->string('nama_tim', 100)->nullable()->after('id_lomba');
        });

        // 2. Buat tabel anggota_pendaftaran untuk menyimpan daftar anggota tim
        Schema::create('anggota_pendaftaran', function (Blueprint $table) {
            $table->id('id_anggota');
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('nama', 100);
            $table->string('no_hp', 20);
            $table->timestamps();

            $table->foreign('id_pendaftaran')
                ->references('id_pendaftaran')
                ->on('pendaftaran')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggota_pendaftaran');

        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropColumn('nama_tim');
        });
    }
};

