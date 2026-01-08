<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('website_visitors', function (Blueprint $table) {
            $table->id();

            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('set null');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('set null');

            $table->string('session_id')->index();
            $table->unsignedInteger('web_user_id')->nullable()->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onDelete('set null');

            $table->string('visitor_hash')->index();

            $table->string('device_type');
            $table->string('os');
            $table->string('browser');
            $table->text('user_agent');

            $table->string('ip_hash')->index();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();

            $table->unsignedInteger('page_views')->default(1);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTimeTz('first_seen_at');
            $table->dateTimeTz('last_seen_at');

            $table->text('referrer_url')->nullable();
            $table->string('landing_page', 4096)->nullable();
            $table->string('exit_page', 4096)->nullable();

            $table->boolean('is_bounce')->default(false);
            $table->boolean('is_new_visitor')->default(true);

            $table->timestampsTz();

            $table->index(['website_id', 'created_at']);
            $table->index(['session_id', 'website_id']);
            $table->index(['visitor_hash', 'website_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_visitors');
    }
};
