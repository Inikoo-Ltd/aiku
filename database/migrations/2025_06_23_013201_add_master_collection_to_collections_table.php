<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 20:58:59 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->unsignedSmallInteger('master_collection_id')->nullable()->index();
            $table->foreign('master_collection_id')->references('id')->on('master_collections')->onUpdate('cascade')->onDelete('cascade');
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropForeign(['master_collection_id']);
            $table->dropColumn('master_collection_id');
        });
    }
};
