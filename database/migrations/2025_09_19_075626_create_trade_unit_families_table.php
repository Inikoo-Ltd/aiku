<?php

use App\Stubs\Migrations\HasAssetCodeDescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasAssetCodeDescription;
    public function up(): void
    {
        Schema::create('trade_unit_families', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id');
            $table->foreign('group_id')->references('id')->on('groups')->onUpdate('cascade')->onDelete('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table = $this->assertCodeDescription($table);
            $table->jsonb('data');
            $table->timestampstz();
            $table->softDeletesTz();
        });

        Schema::table('trade_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('trade_unit_family_id')->nullable()->index();
            $table->foreign('trade_unit_family_id')->references('id')->on('trade_unit_families');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('trade_unit_families');

        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropForeign('trade_unit_family_id');
            $table->dropColumn('trade_unit_family_id');
        });
    }
};
