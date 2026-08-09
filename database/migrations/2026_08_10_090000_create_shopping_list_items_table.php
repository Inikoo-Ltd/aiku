<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('org_supplier_product_id');
            $table->foreign('org_supplier_product_id')->references('id')->on('org_supplier_products');

            $table->unsignedInteger('supplier_product_id');
            $table->foreign('supplier_product_id')->references('id')->on('supplier_products');

            $table->unsignedInteger('supplier_id')->index();
            $table->foreign('supplier_id')->references('id')->on('suppliers');

            $table->unsignedSmallInteger('agent_id')->nullable()->index();
            $table->foreign('agent_id')->references('id')->on('agents');

            $table->decimal('quantity_units', 16, 3);
            $table->unsignedInteger('units_per_pack_snapshot')->nullable();
            $table->unsignedInteger('units_per_carton_snapshot')->nullable();

            $table->string('priority')->default(ShoppingListItemPriorityEnum::NORMAL->value)->index();
            $table->string('state')->default(ShoppingListItemStateEnum::OPEN->value)->index();

            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('added_by_user_id')->nullable();

            $table->text('dismiss_reason')->nullable();
            $table->unsignedInteger('dismiss_proposed_by_user_id')->nullable();
            $table->dateTimeTz('dismiss_proposed_at')->nullable();
            $table->unsignedInteger('resolved_by_user_id')->nullable();
            $table->dateTimeTz('resolved_at')->nullable();

            $table->unsignedInteger('purchase_order_transaction_id')->nullable();
            $table->foreign('purchase_order_transaction_id')->references('id')->on('purchase_order_transactions');

            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('shopping_list_items');

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
    }
};
