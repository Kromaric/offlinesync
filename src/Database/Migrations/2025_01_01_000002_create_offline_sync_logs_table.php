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
            
            // Direction de la sync
            $table->enum('direction', ['push', 'pull', 'bidirectional']);
            
            // Métriques
            $table->integer('items_count')->default(0);
            $table->integer('synced_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('conflicts_count')->default(0);
            
            // Durée
            $table->integer('duration_ms')->nullable();
            
            // Statut global
            $table->boolean('success')->default(true);
            
            // Détails
            $table->json('details')->nullable();
            
            // Index
            $table->index(['synced_at', 'success']);
            $table->index('direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_logs');
    }
};
