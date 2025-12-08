<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Jul 2024 13:35:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasAssetModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasAssetModel;

    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $this->billableFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
