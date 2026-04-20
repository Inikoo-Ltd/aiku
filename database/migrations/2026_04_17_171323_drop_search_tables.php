<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Apr 2026 01:15:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('universal_searches');
        Schema::dropIfExists('iris_searches');
        Schema::dropIfExists('retina_searches');
    }


    public function down(): void
    {
        //
    }
};
