<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 11:36:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('website_id')->index();
            $table->foreign('website_id')->references('id')->on('websites')->nullOnDelete();
            $table->string('trigger')->index();
            $table->string('state')->default(CrawlStateEnum::READY->value)->index();
            $table->boolean('running')->default(false)->index();
            $table->string('type')->default(CrawlTypeEnum::HTML->value)->index();
            $table->string('finish_reason')->nullable()->index();
            $table->timestampTz('start_at')->nullable();
            $table->timestampTz('end_at')->nullable();
            $table->unsignedInteger('urls_processed')->default(0);
            $table->unsignedInteger('urls_found')->default(0);
            $table->unsignedSmallInteger('depth');
            $table->unsignedSmallInteger('concurrency');
            $table->boolean('should_stop')->default(false);
            $table->index(['website_id','running']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawls');
    }
};
