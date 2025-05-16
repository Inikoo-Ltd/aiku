<?php

use App\Enums\Accounting\TopUpPaymentApiPoint\TopUpPaymentApiPointStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $table = $this->deliveryNotesStatsFields($table);
            $table = $this->deliveryNoteItemsStatsFields($table);
        });
    }


    public function down(): void
    {
        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shop) {
                if ($shop->value != 'fulfilment') {
                    // Drop delivery notes stats fields
                    $table->dropColumn([
                        'last_'.$shop->snake().'_shop_delivery_note_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_dispatched_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_order_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_order_dispatched_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_replacement_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_replacement_dispatched_at',
                        'number_'.$shop->snake().'_shop_delivery_notes',
                        'number_'.$shop->snake().'_shop_delivery_notes_state_with_out_of_stock',
                    ]);

                    foreach (DeliveryNoteTypeEnum::cases() as $case) {
                        $table->dropColumn('number_'.$shop->snake().'_shop_delivery_notes_type_'.$case->snake());
                    }

                    foreach (DeliveryNoteStateEnum::cases() as $case) {
                        $table->dropColumn('number_'.$shop->snake().'_shop_delivery_notes_state_'.$case->snake());
                    }

                    // Drop delivery note items stats fields
                    $table->dropColumn([
                        'number_'.$shop->snake().'_shop_delivery_note_items',
                        'number_'.$shop->snake().'_shop_uphold_delivery_note_items',
                    ]);

                    foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                        $table->dropColumn('number_'.$shop->snake().'_shop_delivery_note_items_state_'.$case->snake());
                    }
                }
            }
        });
    }

    public function deliveryNotesStatsFields(Blueprint $table): Blueprint
    {
        foreach (ShopTypeEnum::cases() as $shop) {
            if ($shop->value != 'fulfilment') {
                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_created_at')->nullable();    
                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_dispatched_at')->nullable();

                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_type_order_created_at')->nullable();
                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_type_order_dispatched_at')->nullable();

                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_type_replacement_created_at')->nullable();
                $table->dateTimeTz('last_'.$shop->snake().'_shop_delivery_note_type_replacement_dispatched_at')->nullable();
                $table->unsignedInteger('number_'.$shop->snake().'_shop_delivery_notes')->default(0);

                foreach (DeliveryNoteTypeEnum::cases() as $case) {
                    $table->unsignedInteger('number_'.$shop->snake().'_shop_delivery_notes_type_'.$case->snake())->default(0);
                }
        
                foreach (DeliveryNoteStateEnum::cases() as $case) {
                    $table->unsignedInteger('number_'.$shop->snake().'_shop_delivery_notes_state_'.$case->snake())->default(0);
                }
        
                $table->unsignedInteger('number_'.$shop->snake().'_shop_delivery_notes_state_with_out_of_stock')->default(0);
            }
        }

        return $table;
    }

    public function deliveryNoteItemsStatsFields(Blueprint $table): Blueprint
    {
        foreach (ShopTypeEnum::cases() as $shop) {
            if ($shop->value != 'fulfilment') {
                $table->unsignedBigInteger('number_'.$shop->snake().'_shop_delivery_note_items')->default(0)->comment('transactions including cancelled');
                $table->unsignedBigInteger('number_'.$shop->snake().'_shop_uphold_delivery_note_items')->default(0)->comment('transactions excluding cancelled');

                foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                    $table->unsignedBigInteger('number_'.$shop->snake().'_shop_delivery_note_items_state_'.$case->snake())->default(0);
                }
            }
        }

        return $table;
    }
};