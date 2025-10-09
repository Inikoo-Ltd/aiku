<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 07 Sept 2025 10:48:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->unsignedInteger('collection_address_id')->nullable()->index();
            $table->foreign('collection_address_id')->references('id')->on('addresses');
        });
    }


    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropForeign('delivery_notes_collection_address_id_foreign');
            $table->dropColumn('collection_address_id');
        });
    }
};
