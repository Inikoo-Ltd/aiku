<?php

/*
 * Copyright (c) 2026, Inikoo Ltd
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_email_contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('created_by_user_id')->nullable()->index();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'shop_id', 'email'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_email_contacts');
    }
};
