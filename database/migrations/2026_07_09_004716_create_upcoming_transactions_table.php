<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 09 Jul 2026 13:43:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Ordering\Transaction\UpcomingTransactionStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('upcoming_transactions', function (Blueprint $table) {
            $table->id();

            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');

            $table->unsignedInteger('product_id')->index();
            $table->foreign('product_id')->references('id')->on('products');

            $table->unsignedInteger('order_id')->index()->nullable();
            $table->foreign('order_id')->references('id')->on('orders');
            $table->unsignedInteger('transaction_id')->index()->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions');

            $table->decimal('quantity', 16, 3)->nullable();
            $table->text('public_notes')->nullable();
            $table->text('private_notes')->nullable();

            $table->string('type')->index();
            $table->string('state')->index()->default(UpcomingTransactionStateEnum::READY->value);

            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('upcoming_transactions');
    }
};
