<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->string('jenis_pendaftaran', 30)
                ->default('Individu')
                ->after('nama_lomba');
        });

        // Perbarui data lomba yang sudah ada:
        // Tarik Tambang dan Futsal Sarung menjadi Beregu.
        // Mencakup nama yang sama pada periode berbeda jika memang ada.
        DB::table('lomba')
            ->whereIn('nama_lomba', ['Tarik Tambang', 'Futsal Sarung'])
            ->update(['jenis_pendaftaran' => 'Beregu']);

        DB::table('lomba')
            ->whereNotIn('nama_lomba', ['Tarik Tambang', 'Futsal Sarung'])
            ->update(['jenis_pendaftaran' => 'Individu']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lomba', function (Blueprint $table) {
            $table->dropColumn('jenis_pendaftaran');
        });
    }
};

