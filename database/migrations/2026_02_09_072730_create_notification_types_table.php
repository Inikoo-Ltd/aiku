<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            // Unique identifier code (e.g., 'order.created', 'chat.message')
            $table->string('slug')->unique();
            // Human readable name (e.g., 'New Order Received')
            $table->string('name');
            // Grouping (e.g., 'Commerce', 'CRM', 'System')
            $table->string('category')->default('General');
            $table->string('description')->nullable();
            // Available channels supported by this notification type
            // e.g., ['database', 'mail', 'fcm']
            $table->json('available_channels');
            // Default channels enabled by default for new users
            // e.g., ['database']
            $table->json('default_channels');
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
