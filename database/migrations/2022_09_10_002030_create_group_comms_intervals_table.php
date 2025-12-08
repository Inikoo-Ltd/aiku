<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Nov 2024 12:08:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Stubs\Migrations\HasCommsIntervals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    use HasCommsIntervals;

    public function up(): void
    {
        foreach (OutboxTypeEnum::cases() as $case) {
            $outboxType = Str::snake(str_replace('-', '_', $case->value));

            Schema::create('group_outbox_'.$outboxType.'_intervals', function (Blueprint $table) use ($case) {
                $table->smallIncrements('id');
                $table->unsignedSmallInteger('group_id');
                $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
                $table = $this->getCommsIntervals($case, $table);
                $table->timestampsTz();
            });
        }

    }

    public function down(): void
    {
        foreach (OutboxTypeEnum::cases() as $case) {
            $outboxType = Str::snake(str_replace('-', '_', $case->value));
            Schema::dropIfExists('group_outbox_'.$outboxType.'_intervals');
        }
    }
};
