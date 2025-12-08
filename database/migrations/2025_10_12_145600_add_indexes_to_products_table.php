<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Oct 2025 14:57:48 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Postgres supports IF NOT EXISTS on CREATE INDEX
        DB::statement('CREATE INDEX IF NOT EXISTS products_shop_id_index ON products (shop_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_asset_id_index ON products (asset_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_family_id_index ON products (family_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_sub_department_id_index ON products (sub_department_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS products_department_id_index ON products (department_id)');
    }

    public function down(): void
    {
        // Drop indexes if they exist
        DB::statement('DROP INDEX IF EXISTS products_shop_id_index');
        DB::statement('DROP INDEX IF EXISTS products_asset_id_index');
        DB::statement('DROP INDEX IF EXISTS products_family_id_index');
        DB::statement('DROP INDEX IF EXISTS products_sub_department_id_index');
        DB::statement('DROP INDEX IF EXISTS products_department_id_index');
    }
};
