<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 04:46:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Stubs\Migrations\HasContact;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasContact;
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->index()->nullable();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('name', 256)->nullable();
            $table = $this->contactFields(table: $table, withWebsite: true);
            $table->unsignedInteger('address_id')->nullable()->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->jsonb('location');
            $table->boolean('is_valid_email')->default(false)->index();
            $table->string('state')->index()->default(ProspectStateEnum::NO_CONTACTED->value);
            $table->string('contacted_state')->default(ProspectContactedStateEnum::NA->value);
            $table->string('fail_status')->default(ProspectFailStatusEnum::NA);
            $table->string('success_status')->default(ProspectSuccessStatusEnum::NA);
            $table->boolean('dont_contact_me')->default(false);
            $table->boolean('can_contact_by_email')->index()->default(false);
            $table->boolean('can_contact_by_phone')->default(false);
            $table->boolean('can_contact_by_address')->default(false);
            $table->jsonb('data');

            $table->dateTimeTz('contacted_at')->nullable()->index();
            $table->dateTimeTz('last_contacted_at')->nullable()->index();
            $table->dateTimeTz('last_opened_at')->nullable()->index();
            $table->dateTimeTz('last_clicked_at')->nullable()->index();
            $table->dateTimeTz('dont_contact_me_at')->nullable();
            $table->dateTimeTz('failed_at')->nullable();
            $table->dateTimeTz('registered_at')->nullable();
            $table->dateTimeTz('invoiced_at')->nullable();
            $table->dateTimeTz('last_soft_bounced_at')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
        });
        DB::statement('CREATE INDEX ON prospects USING gin (name gin_trgm_ops) ');
        DB::statement('CREATE INDEX ON prospects USING gin (contact_name gin_trgm_ops) ');
        DB::statement('CREATE INDEX ON prospects USING gin (company_name gin_trgm_ops) ');
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
