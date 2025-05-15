<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 May 2025 15:56:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('combined_label_url')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('combined_label_url')->nullable(false)->change();
        });
    }

};
