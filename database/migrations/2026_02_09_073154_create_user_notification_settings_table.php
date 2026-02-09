<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('notification_type_id')->constrained('notification_types')->onDelete('cascade');
            $table->nullableMorphs('scope');
            $table->boolean('is_enabled')->default(true);
            $table->json('channels')->nullable();
            $table->json('filters')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'notification_type_id', 'scope_type', 'scope_id'], 'unique_user_notif_setting');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('user_notification_settings');
    }
};
