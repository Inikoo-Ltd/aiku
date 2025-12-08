<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Jul 2024 13:35:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasAssetModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetModel;

    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');

            $table->string('type')->index();

            $table->decimal('net_amount', 16, 2);
            $table->decimal('org_net_amount', 16, 2)->nullable();
            $table->decimal('grp_net_amount', 16, 2)->nullable();

            $table->decimal('tax_amount', 16, 2)->nullable();
            $table->decimal('org_tax_amount', 16, 2)->nullable();
            $table->decimal('grp_tax_amount', 16, 2)->nullable();

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
