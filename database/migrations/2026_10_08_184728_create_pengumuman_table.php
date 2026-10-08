
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id('id_pengumuman');

            $table->unsignedBigInteger('id_periode');
            $table->unsignedBigInteger('id_akun');

            $table->string('judul', 150);
            $table->text('isi');

            $table->dateTime('tanggal_publikasi')->nullable();

            $table->enum('status', [
                'Draft',
                'Dipublikasi'
            ])->default('Draft');

            $table->timestamps();

            $table->foreign('id_periode')
                ->references('id_periode')
                ->on('periode')
                ->onDelete('cascade');

            $table->foreign('id_akun')
                ->references('id_akun')
                ->on('akun')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
