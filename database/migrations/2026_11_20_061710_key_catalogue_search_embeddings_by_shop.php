<?php

/*
 * Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('catalogue_search_embeddings', function (Blueprint $table) {
            $table->dropUnique(['model_type', 'model_id']);
            $table->unique(['shop_id', 'model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::table('catalogue_search_embeddings', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'model_type', 'model_id']);
            $table->unique(['model_type', 'model_id']);
        });
    }
};
