<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('partner_shipments', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('org_partner_id');
            $table->foreign('org_partner_id')->references('id')->on('org_partners');
            $table->string('prefix', 32);
            $table->unsignedInteger('first_number');
            $table->jsonb('splits');
            $table->date('date');
            $table->unsignedSmallInteger('number_pallets')->nullable();
            $table->timestampsTz();
            $table->index(['org_partner_id', 'prefix', 'first_number']);
        });

        Schema::create('partner_shipment_delivery_notes', function (Blueprint $table) {
            $table->foreignId('partner_shipment_id')->constrained('partner_shipments')->cascadeOnDelete();
            $table->unsignedInteger('delivery_note_id')->unique();
            $table->foreign('delivery_note_id')->references('id')->on('delivery_notes');
            $table->primary(['partner_shipment_id', 'delivery_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_shipment_delivery_notes');
        Schema::dropIfExists('partner_shipments');
    }
};
