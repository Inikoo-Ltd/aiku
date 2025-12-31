<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 15:01:21 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// need to run this again after changing the shop_type enum
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            $this->deliveryNotesStatsFields($table);
            $this->deliveryNoteItemsStatsFields($table);
        });
    }


    public function down(): void
    {
        Schema::table('organisation_ordering_stats', function (Blueprint $table) {
            foreach (ShopTypeEnum::cases() as $shop) {
                if ($shop->value !== 'fulfilment') {
                    // Drop delivery notes stats fields
                    $columns = [
                        'last_'.$shop->snake().'_shop_delivery_note_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_dispatched_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_order_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_order_dispatched_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_replacement_created_at',
                        'last_'.$shop->snake().'_shop_delivery_note_type_replacement_dispatched_at',
                        'number_'.$shop->snake().'_shop_delivery_notes',
                        'number_'.$shop->snake().'_shop_delivery_notes_state_with_out_of_stock',
                    ];

                    foreach (DeliveryNoteTypeEnum::cases() as $case) {
                        $columns[] = 'number_'.$shop->snake().'_shop_delivery_notes_type_'.$case->snake();
                    }

                    foreach (DeliveryNoteStateEnum::cases() as $case) {
                        $columns[] = 'number_'.$shop->snake().'_shop_delivery_notes_state_'.$case->snake();
                    }

                    // Drop delivery note items stats fields
                    $columns[] = 'number_'.$shop->snake().'_shop_delivery_note_items';
                    $columns[] = 'number_'.$shop->snake().'_shop_uphold_delivery_note_items';

                    foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                        $columns[] = 'number_'.$shop->snake().'_shop_delivery_note_items_state_'.$case->snake();
                    }

                    foreach ($columns as $column) {
                        $truncatedColumn = substr($column, 0, 63);
                        if (Schema::hasColumn('organisation_ordering_stats', $truncatedColumn)) {
                            $table->dropColumn($truncatedColumn);
                        }
                    }
                }
            }
        });
    }

    public function deliveryNotesStatsFields(Blueprint $table): void
    {
        foreach (ShopTypeEnum::cases() as $shop) {
            if ($shop->value !== 'fulfilment') {
                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_created_at', 'dateTimeTz');
                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_dispatched_at', 'dateTimeTz');

                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_type_order_created_at', 'dateTimeTz');
                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_type_order_dispatched_at', 'dateTimeTz');

                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_type_replacement_created_at', 'dateTimeTz');
                $this->addColumnIfNotExists($table, 'last_'.$shop->snake().'_shop_delivery_note_type_replacement_dispatched_at', 'dateTimeTz');
                $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_notes', 'unsignedInteger', 0);

                foreach (DeliveryNoteTypeEnum::cases() as $case) {
                    $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_notes_type_'.$case->snake(), 'unsignedInteger', 0);
                }

                foreach (DeliveryNoteStateEnum::cases() as $case) {
                    $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_notes_state_'.$case->snake(), 'unsignedInteger', 0);
                }

                $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_notes_state_with_out_of_stock', 'unsignedInteger', 0);
            }
        }
    }

    public function deliveryNoteItemsStatsFields(Blueprint $table): void
    {
        foreach (ShopTypeEnum::cases() as $shop) {
            if ($shop->value !== 'fulfilment') {
                $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_note_items', 'unsignedBigInteger', 0, 'transactions including cancelled');
                $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_uphold_delivery_note_items', 'unsignedBigInteger', 0, 'transactions excluding cancelled');

                foreach (DeliveryNoteItemStateEnum::cases() as $case) {
                    $this->addColumnIfNotExists($table, 'number_'.$shop->snake().'_shop_delivery_note_items_state_'.$case->snake(), 'unsignedBigInteger', 0);
                }
            }
        }
    }

    private function addColumnIfNotExists(Blueprint $table, string $column, string $type, mixed $default = null, ?string $comment = null): void
    {
        $truncatedColumn = substr($column, 0, 63);
        if (! Schema::hasColumn('organisation_ordering_stats', $truncatedColumn)) {
            $columnDefinition = $table->$type($column);
            if ($type !== 'dateTimeTz') {
                $columnDefinition->default($default);
            } else {
                $columnDefinition->nullable();
            }

            if ($comment) {
                $columnDefinition->comment($comment);
            }
        }
    }
};
