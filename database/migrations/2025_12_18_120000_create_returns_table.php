<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 12:00:00 Makassar Time.
 * Description: Migration to create returns table for customer order returns
 */

use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Enums\Dispatching\Return\ReturnTypeEnum;
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
            $table->string('state')->index()->default(ReturnStateEnum::IN_PROCESS->value);

            // Reason for return
            $table->string('type')->default(ReturnTypeEnum::CUSTOMER_RETURN->value)->index()->comment('customer_return, quality_issue, wrong_item, etc');
            $table->text('reason')->nullable();

            // Link to original delivery note(s)
            $table->unsignedInteger('delivery_note_id')->nullable()->index();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes')->nullOnDelete();

            // Contact info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('company_name')->nullable();

            // Address for return pickup if needed
            $table->unsignedInteger('address_id')->index()->nullable();
            $table->foreign('address_id')->references('id')->on('addresses');

            // Financial data
            $table->decimal('total_amount', 16, 2)->default(0)->comment('Total value of returned items');
            $table->decimal('refund_amount', 16, 2)->default(0)->comment('Amount to be refunded');

            // Counts
            $table->unsignedSmallInteger('number_items')->default(0);
            $table->decimal('weight', 16, 3)->nullable()->default(0);

            // Timestamps for state transitions
            $table->dateTimeTz('date')->index();
            $table->dateTimeTz('submitted_at')->nullable();
            $table->dateTimeTz('confirmed_at')->nullable();
            $table->dateTimeTz('received_at')->nullable()->comment('When items were physically received');
            $table->dateTimeTz('checked_at')->nullable()->comment('When items were inspected');
            $table->dateTimeTz('completed_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();

            // Notes
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('public_notes')->nullable();

            $table->jsonb('data');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();

            $table->index(['organisation_id', 'state']);
            $table->index(['shop_id', 'state']);
            $table->index(['customer_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
