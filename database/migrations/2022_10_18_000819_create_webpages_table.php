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

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;
    public function up(): void
    {
        Schema::create('webpages', function (Blueprint $table) {
            $table->increments('id');
            $table=$this->groupOrgRelationship($table);
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('url')->index()->collation('und_ns');
            $table->unsignedSmallInteger('level')->index();
            $table->boolean('is_fixed')->default(false);
            $table->string('state')->index()->default(WebpageStateEnum::IN_PROCESS);
            $table->string('type')->index();
            $table->string('purpose')->index();
            $table->unsignedSmallInteger('parent_id')->index()->nullable();
            $table->foreign('parent_id')->references('id')->on('webpages');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites');
            $table->unsignedSmallInteger('unpublished_snapshot_id')->nullable()->index();
            $table->unsignedSmallInteger('live_snapshot_id')->nullable()->index();
            $table->jsonb('compiled_layout');
            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('live_at')->nullable();
            $table->dateTimeTz('closed_at')->nullable();
            $table->string('published_checksum')->nullable()->index();
            $table->boolean('is_dirty')->index()->default(false);
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->timestampsTz();
            $table=$this->softDeletes($table);

            $table->string('source_id')->nullable()->unique();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webpages');
    }
};
