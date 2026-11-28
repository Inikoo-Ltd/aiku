<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('verba_join_usage', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedInteger('user_id');
            $table->string('event', 32);
            $table->unsignedInteger('count')->default(0);
            $table->primary(['date', 'user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verba_join_usage');
    }
};
