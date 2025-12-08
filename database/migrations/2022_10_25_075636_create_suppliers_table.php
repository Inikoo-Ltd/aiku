<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Apr 2023 13:17:21 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasContact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasContact;

    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('agent_id')->nullable()->index();
            $table->foreign('agent_id')->references('id')->on('agents')->onUpdate('cascade')->onDelete('cascade');
            $table->string('scope_type')->index()->comment('Group|Organisation  used for indicate private org suppliers');
            $table->unsignedSmallInteger('scope_id');

            $table->boolean('status')->default(true)->index();
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index()->collation('und_ns');
            $table->string('name')->nullable();
            $table->unsignedInteger('image_id')->nullable();
            $table->foreign('image_id')->references('id')->on('media');
            $table = $this->contactFields(table: $table, withWebsite: true);
            $table->unsignedInteger('address_id')->nullable()->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->jsonb('location');
            $table->unsignedSmallInteger('currency_id');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->jsonb('settings');
            $table->jsonb('data');

            $table->timestampsTz();
            $table->dateTimeTz('archived_at')->nullable();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->softDeletesTz();
            $table->string('source_slug')->index()->nullable();
            $table->string('source_id')->index()->nullable();
            $table->jsonb('sources');
            $table->index(['scope_type', 'scope_id']);
        });
        DB::statement('CREATE INDEX ON suppliers USING gin (name gin_trgm_ops) ');
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
