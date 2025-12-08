<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 29 Aug 2022 12:29:00 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Stubs\Migrations\HasContact;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasContact;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index()->nullable();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('reference')->nullable()->collation('und_ns')->index()->comment('customer public id');
            $table->string('name', 256)->nullable();
            $table = $this->contactFields(table: $table, withWebsite: true);
            $table->unsignedInteger('address_id')->nullable()->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->jsonb('location');
            $table->unsignedInteger('delivery_address_id')->nullable()->index();
            $table->foreign('delivery_address_id')->references('id')->on('addresses');
            $table->string('status')->index();
            $table->string('state')->index()->default(CustomerStateEnum::IN_PROCESS->value);
            $table->decimal('balance', 16)->default(0);
            $table->string('trade_state')->index()->default(CustomerTradeStateEnum::NONE->value)->comment('number of invoices');
            $table->boolean('is_fulfilment')->index()->default(false);
            $table->boolean('is_dropshipping')->index()->default(false);

            $table->timestampTz('last_submitted_order_at')->nullable();
            $table->timestampTz('last_dispatched_delivery_at')->nullable();
            $table->timestampTz('last_invoiced_at')->nullable();

            $table->jsonb('data');
            $table->jsonb('settings');
            $table->text('internal_notes')->nullable();
            $table->text('warehouse_internal_notes')->nullable();
            $table->text('warehouse_public_notes')->nullable();
            $table->unsignedSmallInteger('prospects_sender_email_id')->nullable();
            $table->foreign('prospects_sender_email_id')->references('id')->on('sender_emails');
            $table->unsignedInteger('image_id')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->jsonb('migration_data');
            $table->unique(['shop_id', 'reference']);
        });
        DB::statement('CREATE INDEX ON customers USING gin (name gin_trgm_ops) ');
        DB::statement('CREATE INDEX ON customers USING gin (reference gin_trgm_ops) ');
        DB::statement('CREATE INDEX ON customers USING gin (contact_name gin_trgm_ops) ');
        DB::statement('CREATE INDEX ON customers USING gin (company_name gin_trgm_ops) ');

    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
