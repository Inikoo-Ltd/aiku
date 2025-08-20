<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 19 Sept 2022 23:17:38 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    use HasSoftDeletes;
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->boolean('status')->default(true);
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('auth_type')->default(UserAuthTypeEnum::DEFAULT->value);
            $table->string('contact_name')->nullable()->comment('no-normalised depends on parent');
            $table->string('email')->unique()->nullable();
            $table->text('about')->nullable();
            $table->unsignedSmallInteger('number_models')->default(0);
            $table->unsignedSmallInteger('number_active_models')->default(0);
            $table->unsignedSmallInteger('number_employees')->default(0);
            $table->unsignedSmallInteger('number_active_employees')->default(0);
            $table->unsignedSmallInteger('number_guests')->default(0);
            $table->unsignedSmallInteger('number_active_guests')->default(0);
            $table->unsignedSmallInteger('number_authorised_organisations')->default(0);
            $table->unsignedSmallInteger('number_authorised_shops')->default(0);
            $table->unsignedSmallInteger('number_authorised_fulfilments')->default(0);
            $table->unsignedSmallInteger('number_authorised_warehouses')->default(0);
            $table->unsignedSmallInteger('number_authorised_productions')->default(0);
            $table->rememberToken();
            $table->jsonb('data');
            $table->jsonb('settings');
            $table->boolean('reset_password')->default(false);
            $table->unsignedSmallInteger('language_id')->default(68);
            $table->foreign('language_id')->references('id')->on('languages');
            $table->unsignedInteger('image_id')->nullable();
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table = $this->softDeletes($table);
            $table->string('source_id')->nullable()->unique();
            $table->jsonb('sources');
            $table->string('legacy_password')->nullable()->index()->comment('source password');
            $table->unique(['group_id', 'username']);
        });
        DB::statement('CREATE INDEX ON users USING gin (contact_name gin_trgm_ops) ');
    }


    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
