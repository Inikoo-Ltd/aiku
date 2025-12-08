<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Jun 2024 17:59:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshot_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('snapshot_id');
            $table->foreign('snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('number_web_blocks')->default(0);
            $table->unsignedInteger('number_menu_columns')->default(0);
            $table->unsignedInteger('number_menu_items')->default(0);
            $table->unsignedInteger('number_columns')->default(0);
            $table->unsignedInteger('number_header_columns')->default(0);
            $table->unsignedInteger('number_footer_columns')->default(0);
            $table->unsignedInteger('height_desktop')->nullable();
            $table->unsignedInteger('height_mobile')->nullable();
            $table->unsignedInteger('number_internal_links')->default(0);
            $table->unsignedInteger('number_external_links')->default(0);
            $table->unsignedInteger('number_images')->default(0);
            $table->unsignedBigInteger('filesize')->default(0);
            $table->unsignedInteger('number_slides')->default(0)->comment('for banners');
            $table->unsignedInteger('number_rows')->default(0)->comment('for emails');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshot_stats');
    }
};
