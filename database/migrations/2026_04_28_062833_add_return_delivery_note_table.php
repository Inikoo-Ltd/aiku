<?php

use App\Enums\Dispatching\DeliveryNote\Return\ReturnDeliveryNoteStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('return_delivery_notes', function ($table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            
            $table->unsignedBigInteger('delivery_note_id')->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes');
            $table->unsignedBigInteger('order_id')->index();
            $table->foreign('order_id')->references('id')->on('orders');
            
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->index();

            $table->unsignedSmallInteger('picker_id')->nullable()->index()->comment('Main picker');
            $table->foreign('picker_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('picker_user_id')->nullable()->index();
            $table->foreign('picker_user_id')->references('id')->on('users');

            $table->unsignedSmallInteger('packer_id')->nullable()->index()->comment('Main packer');
            $table->foreign('packer_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('packer_user_id')->nullable()->index();
            $table->foreign('packer_user_id')->references('id')->on('users');

            $table->string('return_state')->default(ReturnDeliveryNoteStateEnum::QUEUED->value);

            $table->dateTimeTz('queued_at')->nullable();
            $table->dateTimeTz('handling_at')->nullable();
            $table->dateTimeTz('picked_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('shipping_notes')->nullable();
            
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('return_delivery_note');
    }
};
