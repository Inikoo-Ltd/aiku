<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Jan 2025 13:00:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_item_audits', function (Blueprint $table) {
            $table->dateTimeTz('date')->nullable()->index();
            $table->decimal('net_amount', 16)->default(0);
            $table->decimal('grp_net_amount', 16)->nullable();
            $table->decimal('org_net_amount', 16)->nullable();
            $table->unsignedSmallInteger('tax_category_id')->nullable()->index();
            $table->foreign('tax_category_id')->references('id')->on('tax_categories');
            $table->decimal('tax_amount', 16)->nullable();
            $table->decimal('total_amount', 16)->nullable();

            $table->unsignedSmallInteger('number_audited_pallets')->default(0);
            $table->unsignedSmallInteger('number_audited_stored_items')->default(0);
            $table->unsignedSmallInteger('number_audited_stored_items_with_additions')->default(0)->comment('Number of stored items with stock additions (found stock)');
            $table->unsignedSmallInteger('number_audited_stored_items_with_with_subtractions')->default(0)->comment('Number of stored items with stock subtractions (lost stock)');
            $table->unsignedSmallInteger('number_audited_stored_items_with_with_stock_checked')->default(0)->comment('Number of stored items with stock checked (stock was correct)');

            $table->unsignedSmallInteger('number_associated_stored_items')->default(0)->comment('Number of stored items associated to the pallet during the audit');
            $table->unsignedSmallInteger('number_created_stored_items')->default(0)->comment('Number of stored items created and associated to the pallet during the audit');

        });
    }

    public function down(): void
    {
        Schema::table('stored_item_audits', function (Blueprint $table) {
            $table->dropColumn('date');
            $table->dropColumn('net_amount');
            $table->dropColumn('grp_net_amount');
            $table->dropColumn('org_net_amount');
            $table->dropColumn('tax_category_id');
            $table->dropColumn('tax_amount');
            $table->dropColumn('total_amount');
            $table->dropColumn('number_audited_pallets');
            $table->dropColumn('number_audited_stored_items');
            $table->dropColumn('number_audited_stored_items_with_additions');
            $table->dropColumn('number_audited_stored_items_with_with_subtractions');
            $table->dropColumn('number_audited_stored_items_with_with_stock_checked');
            $table->dropColumn('number_associated_stored_items');
            $table->dropColumn('number_created_stored_items');

        });
    }
};
