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
            $table->unsignedInteger('number_platforms')->default(0);
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->unsignedInteger("number_platforms_type_{$platform->value}")->default(0);
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
            $table->dropColumn('number_platforms');
            foreach (PlatformTypeEnum::cases() as $platform) {
                $table->dropColumn("number_platforms_type_{$platform->value}");
            }
        });
    }
};
