<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Mar 2025 11:24:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->nullable()->change();
            $table->unsignedSmallInteger('organisation_id')->nullable()->change();
            $table->unsignedInteger('customer_id')->nullable()->change();
            $table->string('username')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_users', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_id')->index()->change();
            $table->unsignedSmallInteger('organisation_id')->change();
            $table->unsignedInteger('customer_id')->index()->change();
            $table->string('username')->nullable()->change();
        });
    }
};
