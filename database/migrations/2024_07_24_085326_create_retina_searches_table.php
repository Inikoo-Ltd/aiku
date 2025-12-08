<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jul 2024 16:53:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSearchFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasSearchFields;

    public function up(): void
    {
        Schema::create('retina_searches', function (Blueprint $table) {
            $this->webSearchFields($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retina_searches');
    }
};
