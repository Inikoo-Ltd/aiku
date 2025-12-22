<?php

/*
 * Author: Oggie Sutrisna
 * Created: Thu, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create returns table for customer order returns
 */

use App\Enums\GoodsIn\Return\ReturnStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSalesTransactionParents;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    use HasSalesTransactionParents;

    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table = $this->salesTransactionParents($table);

            $table->string('reference')->index();
            $table->string('state')->index()->default(ReturnStateEnum::WAITING_TO_RECEIVE->value);


            $table->decimal('weight', 16)->nullable()->default(0)->comment('actual weight, grams');
            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');


            $table->unsignedSmallInteger('inspector_id')->nullable()->index()->comment('Employee who inspected the return');
            $table->foreign('inspector_id')->references('id')->on('users');
            $table->unsignedSmallInteger('processed_id')->nullable()->index()->comment('Employee who processed the return');
            $table->foreign('processed_id')->references('id')->on('users');

            $table->dateTimeTz('date')->index();

            $table->dateTimeTz('waiting_to_receive_at')->nullable();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('inspected_at')->nullable();
            $table->dateTimeTz('restocked_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('return_reason')->nullable();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
