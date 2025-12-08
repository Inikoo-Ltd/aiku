<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 18 Oct 2022 13:17:58 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Web\Webpage\WebpageStateEnum;
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
        Schema::create('webpages', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('parent_id')->index()->nullable();
            $table->foreign('parent_id')->references('id')->on('webpages');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites');
            $table->string('model_type')->index()->nullable();
            $table->string('model_id')->nullable();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('url')->index()->collation('und_ns');
            $table->string('title')->index();
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('level')->index();
            $table->boolean('is_fixed')->default(false);
            $table->string('state')->index()->default(WebpageStateEnum::IN_PROCESS);
            $table->string('type')->index();
            $table->string('sub_type')->index();

            $table->unsignedInteger('unpublished_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_snapshot_id')->nullable()->index();

            $table->jsonb('published_layout');
            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('live_at')->nullable();
            $table->dateTimeTz('closed_at')->nullable();
            $table->string('published_checksum')->nullable()->index();
            $table->boolean('is_dirty')->index()->default(false);
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->jsonb('migration_data');
            $table->index(['model_type', 'model_id']);
        });

        Schema::table('websites', function ($table) {
            $table->foreign('storefront_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('catalogue_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('products_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('login_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('register_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('basket_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('checkout_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('call_back_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('pricing_id')->references('id')->on('webpages')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpages');
    }
};
