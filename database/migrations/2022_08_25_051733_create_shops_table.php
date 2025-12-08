<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 13:18:26 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('master_shop_id')->nullable()->index();
            $table->foreign('master_shop_id')->references('id')->on('master_shops')->nullOnDelete();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code');
            $table->string('name');
            $table->string('company_name', 256)->nullable();
            $table->string('contact_name', 256)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('identity_document_type')->nullable();
            $table->string('identity_document_number')->nullable();
            $table->unsignedInteger('address_id')->nullable()->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->jsonb('location');
            $table->unsignedInteger('collection_address_id')->nullable()->index();
            $table->foreign('collection_address_id')->references('id')->on('addresses');
            $table->string('state')->index()->default(ShopStateEnum::IN_PROCESS->value);
            $table->string('type')->index();
            $table->date('open_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->unsignedSmallInteger('country_id');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->unsignedSmallInteger('language_id');
            $table->foreign('language_id')->references('id')->on('languages');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->unsignedSmallInteger('timezone_id');
            $table->foreign('timezone_id')->references('id')->on('timezones');
            $table->unsignedInteger('image_id')->nullable();

            $table->unsignedSmallInteger('shipping_zone_schema_id')->nullable();
            $table->unsignedSmallInteger('discount_shipping_zone_schema_id')->nullable();

            $table->jsonb('data');
            $table->jsonb('settings');
            $table->unsignedSmallInteger('sender_email_id')->nullable();
            $table->foreign('sender_email_id')->references('id')->on('sender_emails');
            $table->unsignedSmallInteger('prospects_sender_email_id')->nullable();
            $table->foreign('prospects_sender_email_id')->references('id')->on('sender_emails');
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->timestampsTz();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->unique(['group_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
