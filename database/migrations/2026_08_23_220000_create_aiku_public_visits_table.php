<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('aiku_public_visits', function (Blueprint $table) {
            $table->id();
            $table->dateTimeTz('created_at')->index();
            $table->string('path');
            $table->string('referrer')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('visitor_hash', 16)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiku_public_visits');
    }
};
