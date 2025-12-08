<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 14:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\Web\Banner\BannerStateEnum;
use App\Enums\Web\Banner\BannerTypeEnum;
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
        Schema::create('banners', function (Blueprint $table) {
            $table->increments('id');

            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('web_block_id')->nullable()->index();
            $table->foreign('web_block_id')->references('id')->on('web_blocks')->onUpdate('cascade')->onDelete('cascade');

            $table->ulid()->index();
            $table->string('type')->default(BannerTypeEnum::LANDSCAPE->value);

            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name')->collation('und_ns_ci');
            $table->string('state')->default(BannerStateEnum::UNPUBLISHED->value);

            $table->unsignedInteger('unpublished_snapshot_id')->nullable()->index();
            $table->foreign('unpublished_snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('live_snapshot_id')->nullable()->index();
            $table->foreign('live_snapshot_id')->references('id')->on('snapshots')->onUpdate('cascade')->onDelete('cascade');

            $table->dateTimeTz('date')->index();
            $table->dateTimeTz('live_at')->nullable();
            $table->dateTimeTz('switch_off_at')->nullable();
            $table->jsonb('compiled_layout');
            $table->jsonb('data');
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media');
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
