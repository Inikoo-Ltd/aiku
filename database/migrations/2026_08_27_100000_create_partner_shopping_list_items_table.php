<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
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
        Schema::table('org_partners', function (Blueprint $table) {
            $table->jsonb('data')->default('{}');
        });

        Schema::create('partner_shopping_list_items', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('org_partner_id')->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners');

            $table->unsignedSmallInteger('partner_organisation_id')->index();
            $table->foreign('partner_organisation_id')->references('id')->on('organisations');

            $table->unsignedInteger('stock_id')->index();
            $table->foreign('stock_id')->references('id')->on('stocks');

            $table->unsignedInteger('org_stock_id');
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');

            $table->decimal('quantity', 16, 3)->comment('denominated in SKOs (org stock packs)');

            $table->string('priority')->default(ShoppingListItemPriorityEnum::NORMAL->value)->index();
            $table->string('state')->default(ShoppingListItemStateEnum::OPEN->value)->index();

            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('added_by_user_id')->nullable();

            $table->unsignedInteger('transaction_id')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions');

            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('partner_shopping_list_items');

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_shopping_list_items');
        Schema::table('org_partners', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }
};
