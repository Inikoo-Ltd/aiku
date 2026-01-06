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
        Schema::create('website_page_views', function (Blueprint $table) {
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

            // Visitor session
            $table->unsignedBigInteger('website_visitor_id')->index();
            $table->foreign('website_visitor_id')
                ->references('id')->on('website_visitors')
                ->onDelete('cascade');

            // Canonical page
            $table->unsignedInteger('webpage_id')->nullable()->index();
            $table->foreign('webpage_id')
                ->references('id')->on('webpages')
                ->onDelete('set null');

            // Raw data
            $table->string('page_url', 4096);
            $table->string('page_path', 2048)->index();

            // Snapshot ringan
            $table->string('page_type')->nullable()->index();
            $table->string('page_sub_type')->nullable()->index();

            // Time
            $table->date('view_date')->index();
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->timestampsTz();

            $table->index(['webpage_id', 'view_date']);
            $table->index(['website_id', 'view_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_views');
    }
};
