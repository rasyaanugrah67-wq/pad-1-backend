<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengumuman', function (Blueprint $table) {
            $table->enum('target_penerima', ['Semua', 'RT', 'RW'])->default('Semua')->after('status');
            $table->unsignedBigInteger('id_rt')->nullable()->after('target_penerima');
            $table->unsignedBigInteger('id_rw')->nullable()->after('id_rt');
            $table->foreign('id_rt')->references('id_rt')->on('rt')->nullOnDelete();
            $table->foreign('id_rw')->references('id_rw')->on('rw')->nullOnDelete();
        });

        Schema::table('notifikasi', function (Blueprint $table) {
            $table->unsignedBigInteger('id_pengumuman')->nullable()->after('id_warga');
            $table->foreign('id_pengumuman')->references('id_pengumuman')->on('pengumuman')->nullOnDelete();
            $table->unique(['id_pengumuman', 'id_warga']);
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropUnique(['id_pengumuman', 'id_warga']);
            $table->dropForeign(['id_pengumuman']);
            $table->dropColumn('id_pengumuman');
        });

        Schema::table('pengumuman', function (Blueprint $table) {
            $table->dropForeign(['id_rt']);
            $table->dropForeign(['id_rw']);
            $table->dropColumn(['target_penerima', 'id_rt', 'id_rw']);
        });
    }
};
