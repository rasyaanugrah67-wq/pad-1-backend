<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rw', function (Blueprint $table) {
            $table->string('nama_ketua', 150)->nullable()->after('nama_rw');
            $table->string('nomor_whatsapp', 20)->nullable()->after('nama_ketua');
        });

        Schema::table('rt', function (Blueprint $table) {
            $table->string('nama_ketua', 150)->nullable()->after('nama_rt');
            $table->string('nomor_whatsapp', 20)->nullable()->after('nama_ketua');
        });
    }

    public function down(): void
    {
        Schema::table('rt', function (Blueprint $table) {
            $table->dropColumn(['nama_ketua', 'nomor_whatsapp']);
        });

        Schema::table('rw', function (Blueprint $table) {
            $table->dropColumn(['nama_ketua', 'nomor_whatsapp']);
        });
    }
};
