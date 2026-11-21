<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_pembayaran', function (Blueprint $table) {
            $table->id('id_pengaturan');
            $table->string('nama_bank', 100)->nullable();
            $table->string('nomor_rekening', 50)->nullable();
            $table->string('atas_nama', 150)->nullable();
            $table->string('qris_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_pembayaran');
    }
};
