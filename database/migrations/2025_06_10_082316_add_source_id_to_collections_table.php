<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 16:23:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->string('state')->index()->default(CollectionStateEnum::IN_PROCESS->value);
            $table->string('source_id')->nullable()->unique();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->dropColumn(['parent_type', 'parent_id']);
        });
    }


    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn(['source_id', 'fetched_at', 'last_fetched_at']);
            $table->string('parent_type')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
        });
    }
};
