<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Nov 2025 22:27:38 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('model_has_tags', function (Blueprint $table) {
            // Single-column index on tag_id
            $table->index('tag_id', 'mht_tag_id_index');

            // Composite index on (tag_id, model_id)
            $table->index(['tag_id', 'model_id'], 'mht_tag_id_model_id_index');

            // Composite index on (shop_id, is_for_sale)
            $table->index(['shop_id', 'is_for_sale'], 'mht_shop_is_for_sale_index');
        });
    }

    public function down(): void
    {
        Schema::table('model_has_tags', function (Blueprint $table) {
            $table->dropIndex('mht_tag_id_index');
            $table->dropIndex('mht_tag_id_model_id_index');
            $table->dropIndex('mht_shop_is_for_sale_index');
        });
    }
};
