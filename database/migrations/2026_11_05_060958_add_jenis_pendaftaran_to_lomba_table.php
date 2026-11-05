<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('lomba', 'jenis_pendaftaran')) {
            Schema::table('lomba', function (Blueprint $table) {
                $table->string('jenis_pendaftaran', 30)
                    ->default('individu')
                    ->after('nama_lomba');
            });
        }
    }

    public function down(): void
    {
       
    }
};