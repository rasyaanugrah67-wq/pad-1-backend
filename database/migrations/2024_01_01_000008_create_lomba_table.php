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
        Schema::create('lomba', function (Blueprint $table) {
            $table->id('id_lomba');
            $table->unsignedBigInteger('id_periode');
            $table->unsignedBigInteger('id_kategori');
            $table->string('nama_lomba', 100);
            $table->text('deskripsi')->nullable();
            $table->string('gambar')->nullable();
            $table->enum('status', ['Dibuka', 'Selesai', 'Nonaktif'])->default('Dibuka');
            $table->timestamps();

            $table->foreign('id_periode')->references('id_periode')->on('periode')->onDelete('cascade');
            $table->foreign('id_kategori')->references('id_kategori')->on('kategori_lomba')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lomba');
    }
};

