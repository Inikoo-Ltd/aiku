<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Feb 2025 14:30:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->unsignedBigInteger('shopify_user_id')->nullable();
            $table->foreign('shopify_user_id')->references('id')->on('shopify_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_returns', function (Blueprint $table) {
            $table->dropColumn('shopify_user_id');
        });
    }
};
