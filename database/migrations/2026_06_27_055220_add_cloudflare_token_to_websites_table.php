<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 21:25:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {

    public function up(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->text('cloudflare_token')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn('cloudflare_token');
        });
    }
};
