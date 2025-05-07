<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:17:56 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('top_up_payment_api_points', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('top_up_id')->nullable()->index();
            $table->foreign('top_up_id')->references('id')->on('top_ups')->nullOnUpdate();
            $table->unsignedBigInteger('payment_account_shop_id')->index();
            $table->foreign('payment_account_shop_id')->references('id')->on('payment_account_shop')->onDelete('cascade');
            $table->ulid()->index();
            $table->jsonb('data');

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('top_up_payment_api_points');
    }
};
