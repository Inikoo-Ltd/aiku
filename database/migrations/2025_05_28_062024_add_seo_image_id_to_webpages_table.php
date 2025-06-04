<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 May 2025 08:36:53 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('seo_image_id')->nullable();
            $table->foreign('seo_image_id')->references('id')->on('media')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['seo_image_id']);
            $table->dropColumn('seo_image_id');
        });
    }
};
