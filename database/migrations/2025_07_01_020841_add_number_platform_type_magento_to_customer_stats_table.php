<?php

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            if (Schema::hasColumn('customer_stats', "number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())) {
                $table->unsignedInteger("number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_stats', function (Blueprint $table) {
            if (Schema::hasColumn('customer_stats', "number_platforms_type_". PlatformTypeEnum::MAGENTO->snake())) {
                $table->dropColumn(["number_platforms_type_". PlatformTypeEnum::MAGENTO->snake()]);
            }
        });
    }
};
