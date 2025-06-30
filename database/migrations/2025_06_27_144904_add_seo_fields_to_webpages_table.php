<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Jun 2025 15:49:13 British Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('breadcrumb_label')->nullable();
            $table->text('llms_description')->nullable();
            $table->jsonb('structured_data')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn(['breadcrumb_label', 'seo_title', 'seo_description', 'llms_description', 'structured_data']);
        });
    }
};
