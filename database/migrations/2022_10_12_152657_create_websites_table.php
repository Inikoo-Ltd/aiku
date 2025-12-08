<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 12 Oct 2022 17:36:20 Central European Summer Time, Benalmádena, Malaga Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\Web\Website\WebsiteCloudflareStatusEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
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
        Schema::create('websites', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('type')->index();
            $table->string('code')->collation('und_ns')->index();
            $table->string('name')->collation('und_ns')->index();
            $table->string('state')->default(WebsiteStateEnum::IN_PROCESS->value)->index();
            $table->boolean('status')->default(false);
            $table->string('domain')->collation('und_ns');
            $table->jsonb('settings');
            $table->jsonb('data');
            $table->jsonb('structure');
            $table->jsonb('layout');
            $table->jsonb('published_layout');

            $table->unsignedInteger('unpublished_header_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_header_snapshot_id')->nullable()->index();
            $table->string('published_header_checksum')->nullable()->index();
            $table->boolean('header_is_dirty')->index()->default(false);

            $table->unsignedInteger('unpublished_footer_snapshot_id')->nullable()->index();
            $table->unsignedInteger('live_footer_snapshot_id')->nullable()->index();
            $table->string('published_footer_checksum')->nullable()->index();
            $table->boolean('footer_is_dirty')->index()->default(false);

            $table->unsignedSmallInteger('current_layout_id')->index()->nullable();
            $table->unsignedInteger('logo_id')->nullable();

            $table->timestampTz('launched_at')->nullable();
            $table->timestampTz('closed_at')->nullable();

            $table->unsignedInteger('storefront_id')->index()->nullable();
            $table->unsignedInteger('catalogue_id')->index()->nullable();
            $table->unsignedInteger('products_id')->index()->nullable();
            $table->unsignedInteger('login_id')->index()->nullable();
            $table->unsignedInteger('register_id')->index()->nullable();
            $table->unsignedInteger('basket_id')->index()->nullable();
            $table->unsignedInteger('checkout_id')->index()->nullable();
            $table->unsignedInteger('call_back_id')->index()->nullable();
            $table->unsignedInteger('appointment_id')->index()->nullable();
            $table->unsignedInteger('pricing_id')->index()->nullable();

            $table->string('cloudflare_id')->index()->nullable();
            $table->string('cloudflare_status')->nullable()->default(WebsiteCloudflareStatusEnum::NOT_SET->value);

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->unique(['group_id', 'code']);
            $table->unique(['organisation_id', 'name']);
            $table->unique(['organisation_id', 'domain']);
        });
        DB::statement("CREATE INDEX ON websites (lower('code')) ");
        DB::statement("CREATE INDEX ON websites (lower('domain')) ");

    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
