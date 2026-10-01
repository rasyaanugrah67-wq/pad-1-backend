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
        Schema::create('dokumentasi', function (Blueprint $table) {
            $table->id('id_dokumentasi');
            $table->unsignedBigInteger('id_lomba')->nullable();
            $table->unsignedBigInteger('id_panitia')->nullable();
            $table->enum('jenis_file', ['Foto', 'Video'])->default('Foto');
            $table->string('file');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_lomba')->references('id_lomba')->on('lomba')->onDelete('cascade');
            $table->foreign('id_panitia')->references('id_panitia')->on('kepanitiaan')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumentasi');
    }
};

