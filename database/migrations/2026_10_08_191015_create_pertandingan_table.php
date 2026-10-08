
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertandingan', function (Blueprint $table) {
            $table->id('id_pertandingan');

            $table->unsignedBigInteger('id_lomba');

            $table->string('peserta_1', 150);
            $table->string('peserta_2', 150);

            $table->dateTime('waktu_mulai');

            $table->enum('status', [
                'Dijadwalkan',
                'Berlangsung',
                'Selesai'
            ])->default('Dijadwalkan');

            $table->timestamps();

            $table->foreign('id_lomba')
                ->references('id_lomba')
                ->on('lomba')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertandingan');
    }
};
