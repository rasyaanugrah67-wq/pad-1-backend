<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tim_lomba', function (Blueprint $table) {
            $table->id('id_tim');

            $table->unsignedBigInteger('id_lomba');

            $table->string('nama_tim', 100);

            $table->timestamps();

            $table->foreign('id_lomba')
                ->references('id_lomba')
                ->on('lomba')
                ->onDelete('cascade');
        });

        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_tim')
                ->nullable()
                ->after('nama_tim');

            $table->foreign('id_tim')
                ->references('id_tim')
                ->on('tim_lomba')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropForeign(['id_tim']);
            $table->dropColumn('id_tim');
        });

        Schema::dropIfExists('tim_lomba');
    }
};