<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Oct 2025 12:16:10 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
   
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'ufi_number')) {
                $table->string('ufi_number')->nullable();
            }
            if (!Schema::hasColumn('products', 'scpn_number')) {
                $table->string('scpn_number')->nullable();
            }
        });
    }

  
    public function down(): void
    {
        // Intentionally left blank to avoid dropping columns that may have been
        // created by earlier migrations. This migration only adds columns if
        // they do not already exist, so no rollback action is necessary here.
    }
};
