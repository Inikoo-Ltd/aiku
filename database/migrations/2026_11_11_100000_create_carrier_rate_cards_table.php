<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
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
        Schema::create('carrier_rate_cards', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->string('carrier');
            $table->string('service');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('volumetric_kg_per_m3');
            $table->decimal('max_kg', 8, 2);
            $table->decimal('surcharge_percent', 5, 2)->default(0)->comment('fuel/energy and other % surcharges on the base price');
            $table->jsonb('zones')->comment('[{name, match: [{country, postcodes: [prefix]}], bands: {kg: price}, extra_kg}]');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'carrier', 'service', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_rate_cards');
    }
};
