<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Jun 2025 17:01:39 British Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->unsignedInteger('redirect_webpage_id')->index()->nullable();
            $table->foreign('redirect_webpage_id')->references('id')->on('webpages');
        });
    }


    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropForeign(['redirect_webpage_id']);
            $table->dropIndex(['redirect_webpage_id']);
            $table->dropColumn('redirect_webpage_id');
        });
    }
};
