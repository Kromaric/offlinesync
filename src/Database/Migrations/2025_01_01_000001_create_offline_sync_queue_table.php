<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_queue', function (Blueprint $table) {
            $table->id();
            
            // Resource identification
            $table->string('resource');
            $table->string('resource_id')->nullable();

            // Operation type
            $table->enum('operation', ['create', 'update', 'delete']);

            // Data
            $table->json('payload');
            $table->string('hash')->unique();

            // Status
            $table->enum('status', ['pending', 'syncing', 'synced', 'failed'])
                  ->default('pending');
            $table->integer('retry_count')->default(0);

            // Timestamps
            $table->timestamp('created_at');
            $table->timestamp('synced_at')->nullable();

            // Errors
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            // Performance indexes
            $table->index(['status', 'created_at']);
            $table->index(['resource', 'status']);
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_queue');
    }
};
