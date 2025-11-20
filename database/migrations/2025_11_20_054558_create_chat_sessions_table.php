<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
           $table->increments('id');
           // Foreign Keys
            $table->unsignedInteger('web_user_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();

            // Session Details
            $table->char('session_uuid', 36)->unique();
            $table->enum('status', ['active', 'waiting', 'resolved', 'transferred', 'closed'])
                  ->default('active');
            $table->string('guest_identifier', 255)->nullable();
            $table->string('ai_model_version', 50)->nullable();
            $table->string('language', 10)->default('en');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                  ->default('normal');
            $table->enum('closed_by', ['user', 'agent', 'system'])->nullable();

            // Timestamps
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();

            $table->timestampsTz();

            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
