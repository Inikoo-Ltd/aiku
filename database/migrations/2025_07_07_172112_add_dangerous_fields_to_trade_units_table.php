<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Jul 2025 18:38:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasDangerousGoodsFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDangerousGoodsFields;

    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $this->addDangerousGoodsFields($table);
        });
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn($this->getDangerousGoodsFieldNames());
        });
    }
};
