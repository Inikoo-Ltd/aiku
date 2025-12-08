<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_user_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->onUpdate('cascade')->onDelete('set null');
            $table->unsignedInteger('web_user_id')->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onUpdate('cascade')->onDelete('set null');
            $table->dateTimeTz('date');
            $table->string('route_name');
            $table->jsonb('route_params');
            $table->string('os');
            $table->string('device');
            $table->string('browser');
            $table->string('ip_address');
            $table->jsonb('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_requests');
    }
};
