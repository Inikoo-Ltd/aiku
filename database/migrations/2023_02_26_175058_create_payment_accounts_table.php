<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 09:51:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
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
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('payment_service_provider_id')->index();
            $table->foreign('payment_service_provider_id')->references('id')->on('payment_service_providers');
            $table->unsignedInteger('org_payment_service_provider_id')->index()->nullable();
            $table->foreign('org_payment_service_provider_id')->references('id')->on('org_payment_service_providers');
            $table->string('type')->index();
            $table->string('code')->index()->collation('und_ns');
            $table->string('name')->index()->collation('und_ns');
            $table->boolean('is_accounts')->default(false);
            $table->jsonb('data');
            $table->dateTimeTz('last_used_at')->nullable();
            $table->timestampstz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_id')->index()->nullable();
            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
