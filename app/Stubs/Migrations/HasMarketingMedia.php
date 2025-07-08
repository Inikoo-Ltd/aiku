<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 06:24:33 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasMarketingMedia
{
    public function addMarketingMediaFields(Blueprint $table): void
    {
        $table->unsignedInteger('front_image_id')->nullable()->index()->index();
        $table->foreign('front_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('34_image_id')->nullable()->index();
        $table->foreign('34_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('left_image_id')->nullable()->index();
        $table->foreign('left_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('right_image_id')->nullable()->index();
        $table->foreign('right_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('back_image_id')->nullable()->index();
        $table->foreign('back_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('top_image_id')->nullable()->index();
        $table->foreign('top_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('bottom_image_id')->nullable()->index();
        $table->foreign('bottom_image_id')->references('id')->on('media')->nullOnDelete();
        $table->unsignedInteger('size_comparison_image_id')->nullable()->index();
        $table->foreign('size_comparison_image_id')->references('id')->on('media')->nullOnDelete();
        $table->string('video_url')->nullable()->index();
    }


    public function getMarketingMediaFieldNames(): array
    {
        return [
            'front_image_id',
            '34_image_id',
            'left_image_id',
            'right_image_id',
            'back_image_id',
            'top_image_id',
            'bottom_image_id',
            'size_comparison_image_id',
            'video_url'
        ];
    }
}
