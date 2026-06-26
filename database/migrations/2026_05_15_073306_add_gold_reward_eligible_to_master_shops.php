<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 May 2026 18:15:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->boolean('gold_reward_eligible')->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->dropColumn(['gold_reward_eligible']);
        });
    }
};
