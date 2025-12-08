<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 18:22:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasOrderFields;
use App\Stubs\Migrations\HasSalesTransactionParents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasOrderFields;
    use HasSalesTransactionParents;

    public function up(): void
    {
        Schema::create('fulfilment_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->string('parent_type')->index();
            $table->unsignedInteger('parent_id');

            $table->unsignedInteger('fulfilment_id')->nullable()->index();
            $table->foreign('fulfilment_id')->references('id')->on('fulfilments')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('fulfilment_customer_id')->nullable()->index();
            $table->foreign('fulfilment_customer_id')->references('id')->on('fulfilment_customers')->onUpdate('cascade')->onDelete('cascade');

            $table->string('type')->idnex();
            $table->unsignedInteger('asset_id');
            $table->foreign('asset_id')->references('id')->on('assets')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('historic_asset_id');
            $table->foreign('historic_asset_id')->references('id')->on('historic_assets')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('rental_agreement_clause_id')->nullable()->index();

            $table->decimal('quantity', 10, 3);

            $table = $this->orderMoneyFields($table);
            $table->boolean('is_auto_assign')->default(false)->index();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->index(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_transactions');
    }
};
