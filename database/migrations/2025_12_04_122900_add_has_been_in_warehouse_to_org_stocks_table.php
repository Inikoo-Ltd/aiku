<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Dec 2025 15:43:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('org_stocks')) {
            return;
        }

        if (! Schema::hasColumn('org_stocks', 'has_been_in_warehouse')) {
            Schema::table('org_stocks', function (Blueprint $table) {
                $table->boolean('has_been_in_warehouse')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('org_stocks')) {
            return;
        }

        if (Schema::hasColumn('org_stocks', 'has_been_in_warehouse')) {
            Schema::table('org_stocks', function (Blueprint $table) {
                // Drop index then column
                $table->dropIndex(['has_been_in_warehouse']);
                $table->dropColumn(['has_been_in_warehouse']);
            });
        }
    }
};
