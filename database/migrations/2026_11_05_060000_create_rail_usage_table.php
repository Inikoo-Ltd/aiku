<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('rail_usage', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedInteger('user_id');
            $table->string('view', 16);
            $table->string('button', 40)->default('');
            $table->unsignedInteger('clicks')->default(0);
            $table->primary(['date', 'user_id', 'view', 'button']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rail_usage');
    }
};
