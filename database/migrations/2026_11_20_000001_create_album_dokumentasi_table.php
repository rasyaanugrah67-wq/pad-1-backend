<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('album_dokumentasi', function (Blueprint $table) {
            $table->id('id_album');
            $table->unsignedBigInteger('id_periode')->nullable();
            $table->unsignedBigInteger('id_lomba')->nullable();
            $table->string('nama_album', 150);
            $table->date('tanggal_album')->nullable();
            $table->string('cover')->nullable();
            $table->timestamps();

            $table->foreign('id_periode')->references('id_periode')->on('periode')->nullOnDelete();
            $table->foreign('id_lomba')->references('id_lomba')->on('lomba')->nullOnDelete();
            $table->index('nama_album');
        });

        Schema::table('dokumentasi', function (Blueprint $table) {
            $table->unsignedBigInteger('id_album')->nullable()->after('id_dokumentasi');
            $table->foreign('id_album')->references('id_album')->on('album_dokumentasi')->nullOnDelete();
            $table->index('id_album');
        });
    }

    public function down(): void
    {
        Schema::table('dokumentasi', function (Blueprint $table) {
            $table->dropForeign(['id_album']);
            $table->dropIndex(['id_album']);
            $table->dropColumn('id_album');
        });

        Schema::dropIfExists('album_dokumentasi');
    }
};
