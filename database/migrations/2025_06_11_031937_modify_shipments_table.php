<?php

use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('pdf_label', 'label');
            $table->string('label_type')->index()->default(ShipmentLabelTypeEnum::NA->value)->after('label');
        });
        DB::table('shipments')
            ->whereNotNull('label')
            ->update(['label_type' => ShipmentLabelTypeEnum::PDF->value]);
    }


    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->renameColumn('label', 'pdf_label');
            $table->dropColumn('label_type');
        });
    }
};
