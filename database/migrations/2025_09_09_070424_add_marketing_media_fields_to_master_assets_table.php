<?php

use App\Stubs\Migrations\HasMarketingMedia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasMarketingMedia;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $this->addMarketingMediaFields($table);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('master_assets', function (Blueprint $table) {
            $table->dropColumn($this->getMarketingMediaFieldNames());
        });
    }
};
