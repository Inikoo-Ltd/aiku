<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Sep 2023 13:04:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditsTable extends Migration
{
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $morphPrefix = config('audit.user.morph_prefix', 'user');
            $table->id();
            $table->unsignedSmallInteger('group_id')->nullable()->index();
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->unsignedSmallInteger('website_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->string($morphPrefix.'_type')->nullable();
            $table->unsignedInteger($morphPrefix.'_id')->nullable();
            $table->jsonb('tags');
            $table->morphs('auditable');
            $table->string('event');
            $table->string('comments')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('data')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress()->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
        });
    }

    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $table = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->drop($table);
    }
}
