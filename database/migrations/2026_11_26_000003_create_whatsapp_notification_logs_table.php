<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_type', 50)->index();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('id_warga')->nullable();
            $table->string('dedup_key', 191)->unique();
            $table->enum('delivery_state', ['processing', 'queued', 'skipped', 'failed'])->index();
            $table->boolean('is_test_mode')->default(false);
            $table->string('provider_process', 50)->nullable();
            $table->string('provider_detail', 255)->nullable();
            $table->string('error_reason', 255)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->foreign('id_warga')
                ->references('id_warga')
                ->on('warga')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_logs');
    }
};
