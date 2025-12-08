<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 13:08:53 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasCRMStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasCRMStats;

    public function up(): void
    {
        Schema::create('tag_crm_stats', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('tag_id')->unique();
            $table->foreign('tag_id')->references('id')->on('tags');

            $this->customerStats($table);
            $this->prospectsStats($table);
            $table->timestampsTz();

        });

    }

    public function down(): void
    {
        Schema::dropIfExists('tag_crm_stats');
    }
};
