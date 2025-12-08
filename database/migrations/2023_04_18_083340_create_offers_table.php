<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 11:42:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('offer_campaign_id')->index();
            $table->foreign('offer_campaign_id')->references('id')->on('offer_campaigns');
            $table->string('state')->default(OfferStateEnum::IN_PROCESS->value)->index();
            $table->boolean('status')->default(false)->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code');
            $table->string('name');
            $table->string('type')->index();
            $table->string('trigger_type')->nullable()->index();
            $table->unsignedBigInteger('trigger_id')->nullable()->nullable();
            $table->jsonb('allowances');
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->boolean('is_discretionary')->default(false)->index();
            $table->boolean('is_locked')->default(false)->index();

            $table->timestampsTz();
            $table->datetimeTz('start_at')->nullable();
            $table->datetimeTz('end_at')->nullable();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
            $table->jsonb('source_data');
            $table->index(['trigger_type', 'trigger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
