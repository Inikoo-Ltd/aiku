<?php

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->string('return_state')->default(ReturnDeliveryNoteStateEnum::RECEIVED->value)->change();
        });
    }


    public function down(): void
    {
        Schema::table('return_delivery_notes', function (Blueprint $table) {
            $table->string('return_state')->default(null)->change();
        });
    }
};
