<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('aura_button_usage', function (Blueprint $table) {
            $table->date('date');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('shop_id');
            $table->string('button', 40);
            $table->unsignedInteger('clicks')->default(0);
            $table->primary(['date', 'user_id', 'shop_id', 'button']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aura_button_usage');
    }
};
