<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 22:21:07 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasProductInformation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasProductInformation;

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $this->addProductInformationFields($table);
            $table->dateTimeTz('price_updated_at')->nullable();
            $table->dateTimeTz('available_quantity_updated_at')->nullable();
            $table->dateTimeTz('images_updated_at')->nullable();


        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn($this->getProductInformationFieldNames());
        });
    }
};
