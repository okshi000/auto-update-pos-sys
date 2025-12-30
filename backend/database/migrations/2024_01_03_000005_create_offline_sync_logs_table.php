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
        Schema::create('offline_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid');
            $table->string('idempotency_key')->unique();
            $table->string('entity_type'); // sale, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status'); // pending, synced, failed, duplicate
            $table->json('request_payload');
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('has_conflicts')->default(false);
            $table->json('conflicts')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index(['client_uuid', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_sync_logs');
    }
};
