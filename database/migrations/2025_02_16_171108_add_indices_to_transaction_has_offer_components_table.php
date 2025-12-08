<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Feb 2025 01:11:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_has_offer_components', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('offer_campaign_id');
            $table->index('offer_component_id');
            $table->index('offer_id');

        });
    }

    public function down(): void
    {
        Schema::table('transaction_has_offer_components', function (Blueprint $table) {
            $table->dropIndex('transaction_has_offer_components_order_id_index');
            $table->dropIndex('transaction_has_offer_components_offer_campaign_id_index');
            $table->dropIndex('transaction_has_offer_components_offer_component_id_index');
            $table->dropIndex('transaction_has_offer_components_offer_id_index');
        });
    }
};
