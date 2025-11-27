<?php

use App\Enums\Dropshipping\EbayUserStepEnum;
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
        Schema::table('ebay_users', function (Blueprint $table) {
            $table->string('marketplace')->nullable();
            $table->string('step')->default(EbayUserStepEnum::NAME->value);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ebay_users', function (Blueprint $table) {
            $table->dropColumn('marketplace');
            $table->dropColumn('step');
        });
    }
};
