<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jul 2024 13:54:09 Malaysia Time, Kuala Lumpur, Malaysia
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
        Schema::create('stored_item_audit_deltas', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);

            $table->unsignedInteger('stored_item_audit_id')->nullable()->index();
            $table->foreign('stored_item_audit_id')->references('id')->on('stored_item_audits');

            $table->unsignedInteger('pallet_id')->index();
            $table->foreign('pallet_id')->references('id')->on('pallets');
            $table->unsignedInteger('stored_item_id')->index();
            $table->foreign('stored_item_id')->references('id')->on('stored_items');

            $table->dateTimeTz('audited_at')->nullable();
            $table->unsignedSmallInteger('user_id')->nullable()->comment('User who audited the stock');
            $table->foreign('user_id')->references('id')->on('users');

            $table->decimal('original_quantity')->nullable();
            $table->decimal('audited_quantity');

            $table->string('state')->nullable(); // todo make the enum| In Process, Completed,
            $table->string('audit_type')->nullable(); // todo make the enum| Created, Updated, Deleted, Nochamge

            $table->string('reason')->nullable();
            $table->jsonb('data');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_item_audit_deltas');
    }
};
