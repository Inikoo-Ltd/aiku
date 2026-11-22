<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('peppol_id')->nullable()->comment('Peppol participant id scheme:value, e.g. 0245:2120643690; when null it is derived from the tax number');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('peppol_id')->nullable()->index()->comment('Peppol participant id scheme:value, e.g. 0245:2120643690; when null it is derived from the tax number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('peppol_id');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('peppol_id');
        });
    }
};
