<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('user_logins', function (Blueprint $table) {
            $table->id();

            $table->string('type', 100)->index();
            $table->dateTime('datetime')->index();
            $table->string('username')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->ipAddress()->nullable();
            $table->jsonb('location')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('device_type')->nullable();
            $table->jsonb('platform')->nullable();
            $table->jsonb('browser')->nullable();

            $table->index(['type', 'datetime', 'user_id']);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('user_logins');
    }
};
