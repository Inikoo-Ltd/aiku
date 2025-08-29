<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Aug 2025 09:28:41, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            if (!Schema::hasColumn('languages', 'flag')) {
                $table->string('flag', 32)->nullable();
            }
            
            if (Schema::hasColumn('languages', 'original_name') && !Schema::hasColumn('languages', 'native_name')) {
                $table->renameColumn('original_name', 'native_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            if (Schema::hasColumn('languages', 'flag')) {
                $table->dropColumn('flag');
            }
            if (Schema::hasColumn('languages', 'native_name') && !Schema::hasColumn('languages', 'original_name')) {
                $table->renameColumn('native_name', 'original_name');
            }
        });
    }
};
