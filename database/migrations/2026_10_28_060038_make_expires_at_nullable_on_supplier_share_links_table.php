<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_share_links', function (Blueprint $table) {
            $table->dateTimeTz('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_share_links', function (Blueprint $table) {
            $table->dateTimeTz('expires_at')->nullable(false)->change();
        });
    }
};
