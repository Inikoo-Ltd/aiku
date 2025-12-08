<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 11:01:42 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('currency_id')->index();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->decimal('exchange', 16, 4, true);
            $table->date('date')->index();
            $table->string('source')->nullable()->comment('F:Frankfurter, CB:currencyBeacon, M:manual');
            $table->timestampsTz();
            $table->unique(['currency_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
