<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 11:36:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->boolean('show_in_parent')->nullable()->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('webpages', function (Blueprint $table) {
            $table->dropColumn('show_in_parent');
        });
    }
};
