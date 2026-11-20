<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periode', function (Blueprint $table) {
            $table->date('tanggal_mulai')->nullable()->after('deskripsi');
            $table->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
        });

        Schema::create('pemenang', function (Blueprint $table) {
            $table->id('id_pemenang');

            $table->unsignedBigInteger('id_lomba');
            $table->unsignedBigInteger('id_pendaftaran')->nullable();
            $table->unsignedBigInteger('id_tim')->nullable();

            $table->unsignedTinyInteger('peringkat');
            $table->string('nama_pemenang', 150);
            $table->text('keterangan')->nullable();

            $table->timestamps();

            // Relasi ke lomba: mencegah lomba dihapus
            // jika masih memiliki data pemenang
            $table->foreign('id_lomba')
                ->references('id_lomba')
                ->on('lomba')
                ->restrictOnDelete();

            // Relasi ke pendaftaran
            $table->foreign('id_pendaftaran')
                ->references('id_pendaftaran')
                ->on('pendaftaran')
                ->nullOnDelete();

            // Relasi ke tim lomba
            $table->foreign('id_tim')
                ->references('id_tim')
                ->on('tim_lomba')
                ->nullOnDelete();

            // Satu peringkat untuk setiap lomba
            $table->unique(['id_lomba', 'peringkat']);

            // Index untuk pencarian nama pemenang
            $table->index('nama_pemenang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemenang');

        Schema::table('periode', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_mulai',
                'tanggal_selesai'
            ]);
        });
    }
};