<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('traffic_source_costs', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();

            $table->unsignedInteger('traffic_source_id');
            $table->foreign('traffic_source_id')->references('id')->on('traffic_sources')->cascadeOnDelete();
            $table->unsignedInteger('traffic_source_campaign_id')->nullable();
            $table->foreign('traffic_source_campaign_id')->references('id')->on('traffic_source_campaigns')->nullOnDelete();

            $table->date('date');

            /* `amount` is in the shop's currency so it can be compared against the revenue in
               traffic_source_stats, which is summed from customer_stats and is therefore also in shop
               currency. `org_amount` mirrors the convention used by payments and invoices and lets the
               organisation-scoped listing add up shops that bill in different currencies. */
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('org_amount', 16, 2)->default(0);

            /* Kept so a figure can always be traced back to what the advertiser was actually billed,
               independently of whatever exchange rate was applied on import. */
            $table->decimal('source_amount', 16, 2)->default(0);
            $table->unsignedSmallInteger('source_currency_id');
            $table->foreign('source_currency_id')->references('id')->on('currencies');


            $table->timestampsTz();

            $table->index(['shop_id', 'date']);
            $table->index(['traffic_source_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_costs');
    }
};
