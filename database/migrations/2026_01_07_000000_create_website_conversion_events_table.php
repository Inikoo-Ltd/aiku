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
        Schema::create('website_conversion_events', function (Blueprint $table) {
            $table->id();

            // Context
            $table = $this->groupOrgRelationship($table);

            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')
                ->references('id')->on('shops')
                ->onDelete('cascade');

            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')
                ->references('id')->on('websites')
                ->onDelete('cascade');

            // Session / visitor
            $table->unsignedBigInteger('website_visitor_id')->index();
            $table->foreign('website_visitor_id')
                ->references('id')->on('website_visitors')
                ->onDelete('cascade');

            // Canonical page (best effort)
            $table->unsignedInteger('webpage_id')->nullable()->index();
            $table->foreign('webpage_id')
                ->references('id')->on('webpages')
                ->onDelete('set null');

            // Event
            $table->string('event_type')->index(); // add_to_basket
            $table->unsignedInteger('product_id')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);

            // Raw page data (audit / fallback)
            $table->string('page_url', 4096);
            $table->string('page_path', 2048)->index();

            // Time
            $table->date('event_date')->index();

            $table->timestampsTz();

            $table->index(['event_type', 'event_date']);
            $table->index(['webpage_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_conversion_events');
    }
};
