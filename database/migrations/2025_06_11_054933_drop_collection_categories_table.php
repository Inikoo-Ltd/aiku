<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 13:49:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('collection_collection_category');
        Schema::dropIfExists('collection_category_stats');
        Schema::dropIfExists('collection_category_sales_intervals');
        Schema::dropIfExists('collection_categories');

    }


    public function down(): void
    {
        // These tables would need to be recreated based on their original structure
        // You may want to reference the original creation migrations

    }
};
