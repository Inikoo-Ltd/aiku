<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 23:02:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasSearchFields
{
    use HasGroupOrganisationRelationship;

    public function searchFields(Blueprint $table): Blueprint
    {
        $table->nullableMorphs('model');

        $table->longText('keyword')->nullable();
        $table->longText('keyword_2')->nullable();

        $table->longText('haystack_tier_1')->nullable();
        $table->longText('haystack_tier_2')->nullable();
        $table->longText('haystack_tier_3')->nullable();
        $table->string('status')->default('active');
        $table->float('weight')->default(4);
        $table->dateTime('date')->nullable();

        $table->jsonb('sections');
        $table->jsonb('permissions');
        if ($table->getTable() != 'universal_searches') {
            $table->jsonb('web_users');
        }
        $table->jsonb('result');

        $table->timestampsTz();

        return $table;
    }

    public function webSearchFields(Blueprint $table): Blueprint
    {
        $table->increments('id');
        $table = $this->groupOrgRelationship($table);

        if ($table->getTable() === 'iris_searches') {
            $table->unsignedSmallInteger('website_id')->nullable()->index();
            $table->foreign('website_id')->references('id')->on('websites');
        }

        $table->unsignedInteger('customer_id')->nullable()->index();
        $table->foreign('customer_id')->references('id')->on('customers');

        return $this->searchFields($table);

    }
}
