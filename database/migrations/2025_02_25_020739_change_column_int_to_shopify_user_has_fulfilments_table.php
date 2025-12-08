<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Feb 2025 18:51:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->unsignedBigInteger('shopify_user_id')->change();
            $table->unsignedBigInteger('model_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_user_has_fulfilments', function (Blueprint $table) {
            $table->smallInteger('shopify_user_id')->change();
            $table->smallInteger('model_id')->change();
        });
    }
};
