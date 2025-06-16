<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Jun 2025 12:39:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('pdf_label', 'label');
            $table->string('label_type')->index()->default(ShipmentLabelTypeEnum::NA->value);
        });

    }


    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('label', 'pdf_label');
            $table->dropColumn('label_type');
        });
    }
};
