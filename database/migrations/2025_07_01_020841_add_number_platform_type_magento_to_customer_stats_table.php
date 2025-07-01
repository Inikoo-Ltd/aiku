<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Jul 2025 08:12:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            if (Schema::hasColumn('customer_stats', "number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())) {
                $table->unsignedInteger("number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())->default(0);
            }
        });
    }


    public function down(): void
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            if (Schema::hasColumn('customer_stats', "number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())) {
                $table->dropColumn(["number_platforms_type_". PlatformTypeEnum::MAGENTO->snake()]);
            }
        });
    }
};
