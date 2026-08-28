<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_has_team_members', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('member_user_id');
            $table->foreign('member_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['user_id', 'member_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_team_members');
    }
};
