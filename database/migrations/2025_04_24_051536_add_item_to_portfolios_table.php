<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Apr 2025 14:31:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['type']);
            $table->string('item_code')->nullable()->index()->comment('no normal field used for improve performance on UI search');
            $table->string('item_name')->nullable()->index()->comment('no normal field used for improve performance on UI search');
        });
    }


    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('type')->nullable();
            $table->dropColumn(['item_code', 'item_name']);
        });
    }
};
