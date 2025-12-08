<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 22 Nov 2024 13:13:16 Central Indonesia Time, Sanur, Bali, Indonesia
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
            $outboxType = Str::snake($case->value);

            Schema::create('organisation_outbox_'.$outboxType.'_intervals', function (Blueprint $table) use ($case) {
                $table->smallIncrements('id');
                $table->unsignedSmallInteger('organisation_id');
                $table->foreign('organisation_id')->references('id')->on('organisations')->onUpdate('cascade')->onDelete('cascade');
                $table = $this->getCommsIntervals($case, $table);
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        foreach (OutboxTypeEnum::cases() as $case) {
            $outboxType = Str::snake($case->value);
            Schema::dropIfExists('organisation_outbox_'.$outboxType.'_intervals');
        }
    }
};
