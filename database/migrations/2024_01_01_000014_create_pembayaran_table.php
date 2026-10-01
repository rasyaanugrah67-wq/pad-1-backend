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
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->unsignedBigInteger('id_iuran');
            $table->dateTime('tanggal_bayar');
            $table->decimal('nominal', 12, 2);
            $table->string('metode', 50)->nullable();
            $table->string('bukti_bayar')->nullable();
            $table->enum('status_verifikasi', ['Menunggu', 'Diterima', 'Ditolak'])->default('Menunggu');
            $table->unsignedBigInteger('diverifikasi_oleh')->nullable();
            $table->dateTime('tanggal_verifikasi')->nullable();
            $table->timestamps();

            $table->foreign('id_iuran')->references('id_iuran')->on('iuran')->onDelete('cascade');
            $table->foreign('diverifikasi_oleh')->references('id_akun')->on('akun')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};

