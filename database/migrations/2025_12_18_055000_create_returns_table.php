<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Create returns table for customer order returns
 */

use App\Enums\Dispatching\Return\ReturnStateEnum;
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

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->unsignedInteger('address_id')->index()->nullable();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->unsignedInteger('return_country_id')->index()->nullable();
            $table->foreign('return_country_id')->references('id')->on('countries');

            $table->decimal('weight', 16)->nullable()->default(0)->comment('actual weight, grams');
            $table->unsignedSmallInteger('number_items')->default(0)->comment('current number of items');

            $table->unsignedSmallInteger('receiver_id')->nullable()->index()->comment('Employee who received the return');
            $table->foreign('receiver_id')->references('id')->on('employees');
            $table->unsignedSmallInteger('inspector_id')->nullable()->index()->comment('Employee who inspected the return');
            $table->foreign('inspector_id')->references('id')->on('employees');

            $table->dateTimeTz('date')->index();
            $table->dateTimeTz('received_at')->nullable();
            $table->dateTimeTz('inspecting_at')->nullable();
            $table->dateTimeTz('processed_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('return_reason')->nullable();

            $table->unsignedInteger('platform_id')->nullable()->index();
            $table->foreign('platform_id')->references('id')->on('platforms');
            $table->unsignedInteger('customer_sales_channel_id')->nullable()->index();
            $table->foreign('customer_sales_channel_id')->references('id')->on('customer_sales_channels');

            $table->unsignedSmallInteger('receiver_user_id')->nullable()->index();
            $table->foreign('receiver_user_id')->references('id')->on('users');
            $table->unsignedSmallInteger('inspector_user_id')->nullable()->index();
            $table->foreign('inspector_user_id')->references('id')->on('users');

            $table->integer('estimated_weight')->default(0)->comment('grams');
            $table->integer('effective_weight')->default(0)->comment('Used for UI tables, grams');

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
