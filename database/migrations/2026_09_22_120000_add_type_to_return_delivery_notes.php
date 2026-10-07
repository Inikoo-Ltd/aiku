<?php

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->string('type')
                ->default(ReturnDeliveryNoteTypeEnum::RETURN->value)
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
