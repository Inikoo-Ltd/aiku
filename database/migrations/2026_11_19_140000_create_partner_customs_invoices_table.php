<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::table('partner_shipments', function (Blueprint $table) {
            $table->dropIndex(['org_partner_id', 'prefix', 'first_number']);
            $table->dropColumn(['prefix', 'first_number', 'splits']);
            $table->jsonb('data')->default('{}');
            $table->string('reference')->nullable()->index();
            $table->unsignedSmallInteger('warehouse_id')->nullable()->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->dateTimeTz('customs_invoiced_at')->nullable();
            $table->dateTimeTz('dispatched_at')->nullable();
            $table->dateTimeTz('voided_at')->nullable();
            $table->unsignedSmallInteger('voided_by_user_id')->nullable();
            $table->foreign('voided_by_user_id')->references('id')->on('users');
            $table->text('void_reason')->nullable();
        });

        Schema::create('partner_shipment_invoices', function (Blueprint $table) {
            $table->foreignId('partner_shipment_id')->constrained('partner_shipments')->cascadeOnDelete();
            $table->unsignedInteger('invoice_id')->unique();
            $table->foreign('invoice_id')->references('id')->on('invoices');
            $table->primary(['partner_shipment_id', 'invoice_id']);
        });

        Schema::create('partner_customs_invoices', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('org_partner_id')->index();
            $table->foreign('org_partner_id')->references('id')->on('org_partners');
            $table->foreignId('partner_shipment_id')->constrained('partner_shipments')->cascadeOnDelete();
            $table->string('split', 16);
            $table->string('label');
            $table->boolean('with_origin_declaration')->default(false);
            $table->string('reference')->index();
            $table->date('date')->index();
            $table->unsignedSmallInteger('number_lines')->default(0);
            $table->decimal('units', 16, 3)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('net_weight', 16, 3)->default(0);
            $table->decimal('gross_weight', 16, 3)->default(0);
            $table->timestampsTz();
            $table->unique(['org_partner_id', 'reference']);
        });

        Schema::create('partner_customs_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_customs_invoice_id')->constrained('partner_customs_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('tariff_code')->nullable();
            $table->string('origin', 8)->nullable();
            $table->text('parts')->nullable();
            $table->text('description')->nullable();
            $table->text('local_description')->nullable();
            $table->string('un_numbers')->nullable();
            $table->decimal('units', 16, 3)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('net_weight', 16, 3)->default(0);
            $table->decimal('gross_weight', 16, 3)->default(0);
            $table->timestampsTz();
            $table->index(['partner_customs_invoice_id', 'position']);
        });

        $this->seedSerialReferences();
    }

    /**
     * Two counters per partner: consolidated delivery notes (AROES-C0001...) and customs invoices (AROES0001...). Spain's AROES0075 and AROES0076 were made by hand
     * before aiku numbered them, so its counter starts there (HELP-4482).
     */
    private function seedSerialReferences(): void
    {
        $orgPartners = DB::table('org_partners')
            ->join('organisations as o', 'o.id', '=', 'org_partners.organisation_id')
            ->join('organisations as p', 'p.id', '=', 'org_partners.partner_id')
            ->select(['org_partners.id', 'org_partners.organisation_id', 'o.code as organisation_code', 'p.code as partner_code'])
            ->get();

        foreach ($orgPartners as $orgPartner) {
            $prefix = strtoupper(substr($orgPartner->organisation_code, 0, 3).$orgPartner->partner_code);
            DB::table('serial_references')->insert([
                'container_type'  => 'OrgPartner',
                'container_id'    => $orgPartner->id,
                'organisation_id' => $orgPartner->organisation_id,
                'model'           => 'partner_shipment',
                'serial'          => 0,
                'format'          => $prefix.'-C%04d',
                'data'            => '{}',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            DB::table('serial_references')->insert([
                'container_type'  => 'OrgPartner',
                'container_id'    => $orgPartner->id,
                'organisation_id' => $orgPartner->organisation_id,
                'model'           => 'customs_invoice',
                'serial'          => $prefix === 'AROES' ? 76 : 0,
                'format'          => $prefix.'%04d',
                'data'            => '{}',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('serial_references')->where('container_type', 'OrgPartner')->whereIn('model', ['customs_invoice', 'partner_shipment'])->delete();
        Schema::dropIfExists('partner_customs_invoice_lines');
        Schema::dropIfExists('partner_customs_invoices');
        Schema::dropIfExists('partner_shipment_invoices');
        Schema::table('partner_shipments', function (Blueprint $table) {
            $table->dropForeign(['voided_by_user_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['data', 'reference', 'warehouse_id', 'customs_invoiced_at', 'dispatched_at', 'voided_at', 'voided_by_user_id', 'void_reason']);
            $table->string('prefix', 32)->default('');
            $table->unsignedInteger('first_number')->default(1);
            $table->jsonb('splits')->default('[]');
            $table->index(['org_partner_id', 'prefix', 'first_number']);
        });
    }
};
