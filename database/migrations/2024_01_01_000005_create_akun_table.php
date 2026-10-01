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
        Schema::create('akun', function (Blueprint $table) {
            $table->id('id_akun');
            $table->unsignedBigInteger('id_warga')->unique();
            $table->unsignedBigInteger('id_role');
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->enum('status', ['Aktif', 'Nonaktif'])->default('Aktif');
            $table->dateTime('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('id_warga')->references('id_warga')->on('warga')->onDelete('cascade');
            $table->foreign('id_role')->references('id_role')->on('role')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun');
    }
};

