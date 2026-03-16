<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 15:34:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('picked_bay_has_delivery_notes', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('picked_bay_id')->nullable()->index();
            $table->unsignedInteger('delivery_note_id')->nullable()->index();

            $table->foreign('delivery_note_id')
                ->references('id')->on('delivery_notes')
                ->nullOnDelete();

            $table->foreign('picked_bay_id')
                ->references('id')->on('picked_bays')
                ->nullOnDelete();

            $table->timestampsTz();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picked_bay_has_delivery_notes');
    }
};
