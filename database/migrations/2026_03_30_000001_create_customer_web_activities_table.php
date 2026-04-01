<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('customer_web_activities', function (Blueprint $table) {
            $table->id();

            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->onDelete('cascade');

            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->unsignedInteger('web_user_id')->nullable()->index();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onDelete('set null');

            $table->unsignedBigInteger('website_visitor_id')->nullable()->index();
            $table->foreign('website_visitor_id')->references('id')->on('website_visitors')->onDelete('set null');

            $table->string('activity_type');

            $table->string('page_url', 4096);
            $table->string('page_path', 4096);
            $table->string('page_type')->nullable();
            $table->string('page_sub_type')->nullable();

            $table->unsignedInteger('webpage_id')->nullable()->index();
            $table->foreign('webpage_id')->references('id')->on('webpages')->onDelete('set null');

            $table->unsignedInteger('product_id')->nullable()->index();

            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->date('activity_date')->index();

            $table->timestampsTz();

            $table->index(['customer_id', 'activity_date']);
            $table->index(['customer_id', 'activity_type', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_web_activities');
    }
};
