<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sync_logs', function (Blueprint $table) {
            $table->id();
            
            $table->timestamp('synced_at');
            
            // Sync direction
            $table->enum('direction', ['push', 'pull', 'bidirectional']);

            // Metrics
            $table->integer('items_count')->default(0);
            $table->integer('synced_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('conflicts_count')->default(0);

            // Duration
            $table->integer('duration_ms')->nullable();

            // Overall status
            $table->boolean('success')->default(true);

            // Details
            $table->json('details')->nullable();

            // Indexes
            $table->index(['synced_at', 'success']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_logs');
    }
};
