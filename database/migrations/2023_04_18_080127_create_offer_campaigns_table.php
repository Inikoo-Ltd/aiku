<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 16:22:47 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('offer_campaigns', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('state')->default(OfferCampaignStateEnum::IN_PROCESS->value)->index();
            $table->boolean('status')->default(false)->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code');
            $table->string('name');
            $table->string('type')->index();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->datetimeTz('start_at')->nullable()->index();
            $table->datetimeTz('finish_at')->nullable()->index();

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_campaigns');
    }
};
