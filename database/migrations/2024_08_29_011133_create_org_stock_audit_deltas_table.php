<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 11:54:59 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('org_stock_audit_deltas', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('warehouse_id')->index();
            $table->foreign('warehouse_id')->references('id')->on('warehouses');

            $table->unsignedInteger('org_stock_id')->index();
            $table->foreign('org_stock_id')->references('id')->on('org_stocks');
            $table->unsignedInteger('location_id')->index();
            $table->foreign('location_id')->references('id')->on('locations');

            $table->dateTimeTz('audited_at')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable()->comment('User who audited the stock');
            $table->foreign('user_id')->references('id')->on('users');

            $table->decimal('original_quantity')->nullable();
            $table->decimal('audited_quantity');

            $table->string('type')->index()->comment('Addition, Subtraction, NoChange');

            $table->string('reason')->nullable();
            $table->jsonb('data');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_stock_audit_deltas');
    }
};
