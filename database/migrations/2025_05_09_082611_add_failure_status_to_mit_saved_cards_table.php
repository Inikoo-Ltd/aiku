<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 16:26:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('mit_saved_cards', function (Blueprint $table) {
            $table->string('failure_status')->nullable()->index();
        });
    }


    public function down(): void
    {
        Schema::table('mit_saved_cards', function (Blueprint $table) {
            $table->dropColumn('failure_status');
        });
    }
};
