<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 13:53:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->boolean('is_opt_in')->index()->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('is_opt_in');
        });
    }
};
