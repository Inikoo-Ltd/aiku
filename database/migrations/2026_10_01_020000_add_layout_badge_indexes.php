<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS notifications_notifiable_created_at_index ON notifications (notifiable_type, notifiable_id, created_at)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS products_needs_content_review_index ON products (shop_id) WHERE is_for_sale AND master_product_id IS NOT NULL AND (is_name_reviewed = false OR is_description_title_reviewed = false OR is_description_reviewed = false OR is_description_extra_reviewed = false)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_needs_content_review_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS notifications_notifiable_created_at_index');
    }
};
