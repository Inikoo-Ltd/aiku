<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 13:22:28 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'addresses',
            function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedSmallInteger('group_id')->index();
                $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

                $table->unsignedInteger('usage')->default(0)->comment('usage by models/scopes');
                $table->unsignedInteger('fixed_usage')->default(0)->comment('count usage by fixed models/fixed_scopes');
                $table->unsignedInteger('multiplicity')->default(0)->comment('count address with same checksum');

                // $table->nullableMorphs('owner');
                $table->string('address_line_1', 255)->nullable();
                $table->string('address_line_2', 255)->nullable();
                $table->string('sorting_code')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('dependent_locality')->nullable();
                $table->string('locality')->nullable();
                $table->string('administrative_area')->nullable();
                $table->string('country_code', 2)->nullable()->index();
                $table->unsignedSmallInteger('country_id')->nullable()->index();
                $table->foreign('country_id')->references('id')->on('countries');
                $table->string('checksum')->index()->nullable();
                $table->boolean('is_fixed')->index()->default(false)->comment('Directly related to the model class, (no model_has_addresses entry)');
                $table->string('fixed_scope')->index()->nullable()->comment('Key where address can be shared if have same checksum');

                $table->timestampsTz();
            }
        );

        Schema::create('model_has_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('address_id')->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('scope')->nullable()->index();
            $table->string('sub_scope')->nullable()->index();
            $table->string('label')->nullable();
            $table->boolean('can_ship')->index()->nullable()->comment('e.g. for customer: delivery address');

            $table->boolean('is_historic')->index()->default(false);
            $table->dateTimeTz('valid_until')->nullable()->index();
            $table->timestampsTz();
            $table->index(['model_id', 'model_type']);

        });

        Schema::create('model_has_fixed_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');

            $table->unsignedInteger('address_id')->index();
            $table->foreign('address_id')->references('id')->on('addresses');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('scope')->nullable()->index();
            $table->string('sub_scope')->nullable()->index();
            $table->timestampsTz();
            $table->index(['model_id', 'model_type']);

        });

    }

    public function down(): void
    {
        Schema::dropIfExists('addressables');
        Schema::dropIfExists('addresses');
    }
};
