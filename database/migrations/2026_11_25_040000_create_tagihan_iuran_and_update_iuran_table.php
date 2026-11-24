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
        // 1. Buat tabel master tagihan_iuran
        Schema::create('tagihan_iuran', function (Blueprint $table) {
            $table->id('id_tagihan');
            $table->unsignedBigInteger('id_periode');
            $table->string('nama_tagihan', 150);
            $table->decimal('nominal', 12, 2);
            $table->date('jatuh_tempo');
            $table->text('keterangan')->nullable();
            $table->enum('target', ['Semua', 'Tertentu'])->default('Semua');
            $table->boolean('is_aktif')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('id_periode')
                ->references('id_periode')
                ->on('periode')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id_akun')
                ->on('akun')
                ->onDelete('set null');
        });

        // 2. Tambahkan kolom relasi dan atribut tagihan pada tabel iuran
        Schema::table('iuran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tagihan')
                ->nullable()
                ->after('id_iuran')
                ->index();

            $table->string('nama_iuran', 150)
                ->nullable()
                ->after('id_periode');

            $table->text('keterangan')
                ->nullable()
                ->after('jatuh_tempo');

            $table->boolean('is_aktif')
                ->default(true)
                ->after('status')
                ->index();

            $table->foreign('id_tagihan')
                ->references('id_tagihan')
                ->on('tagihan_iuran')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iuran', function (Blueprint $table) {
            $table->dropForeign(['id_tagihan']);
            $table->dropColumn([
                'id_tagihan',
                'nama_iuran',
                'keterangan',
                'is_aktif',
            ]);
        });

        Schema::dropIfExists('tagihan_iuran');
    }
};
