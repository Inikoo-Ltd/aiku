<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 23:27:45 Central European Summer Time, Mijas Costa, Spain
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $this->billableFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
