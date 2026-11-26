<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logo_hut_ri', function (Blueprint $table) {
            $table->id('id_logo');
            $table->unsignedSmallInteger('tahun')->unique();
            $table->string('nama_logo', 150);
            $table->string('gambar', 500);
            $table->enum('status', ['Aktif', 'Nonaktif'])->default('Nonaktif')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logo_hut_ri');
    }
};
