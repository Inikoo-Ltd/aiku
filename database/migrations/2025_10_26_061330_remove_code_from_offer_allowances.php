<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Nov 2025 09:58:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('offer_allowances', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->renameColumn('target_type', 'target_filter');
            $table->string('target_type')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('offer_allowances', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->renameColumn('target_filter', 'target_type');
            $table->dropColumn('target_type');
        });
    }
};
