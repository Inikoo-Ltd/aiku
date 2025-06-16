<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 11:14:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('ebay_users', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('status')->default(true)->index();
            $table->string('name')->index();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->unsignedBigInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms')->nullOnDelete();
            $table->unsignedBigInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels')->nullOnDelete();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ebay_users');
    }
};
