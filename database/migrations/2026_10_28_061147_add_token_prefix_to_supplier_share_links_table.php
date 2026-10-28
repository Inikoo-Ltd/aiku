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
            $table->string('token_prefix', 6)->nullable()->index()->comment('first letters of the token, so staff can tell links apart; the rest is only hashed');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_share_links', function (Blueprint $table) {
            $table->dropColumn('token_prefix');
        });
    }
};
