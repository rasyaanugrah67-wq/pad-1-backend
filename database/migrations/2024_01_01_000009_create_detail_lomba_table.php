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
        Schema::create('detail_lomba', function (Blueprint $table) {
            $table->id('id_detail');
            $table->unsignedBigInteger('id_lomba')->unique();
            $table->text('deskripsi')->nullable();
            $table->text('alur')->nullable();
            $table->text('syarat')->nullable();
            $table->text('peraturan')->nullable();
            $table->timestamps();

            $table->foreign('id_lomba')->references('id_lomba')->on('lomba')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_lomba');
    }
};

