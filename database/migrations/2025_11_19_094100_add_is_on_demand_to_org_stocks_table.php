<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Nov 2025 09:43:06 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->boolean('is_on_demand')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropIndex(['is_on_demand']);
            $table->dropColumn('is_on_demand');
        });
    }
};
