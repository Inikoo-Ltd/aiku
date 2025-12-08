<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jan 2024 12:27:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sender_emails', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('email_address')->unique();
            $table->unsignedSmallInteger('usage_count')->default(0);
            $table->string('state');
            $table->jsonb('data');
            $table->dateTimeTz('last_verification_submitted_at')->nullable();
            $table->dateTimeTz('verified_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sender_emails');
    }
};
