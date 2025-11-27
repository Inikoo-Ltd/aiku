<?php

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
        Schema::table('master_assets', function (Blueprint $table) {
            $table->string('un_number')->nullable();
            $table->string('un_class')->nullable();
            $table->string('packing_group')->nullable();
            $table->string('proper_shipping_name')->nullable();
            $table->string('hazard_identification_number')->nullable();
            $table->text('gpsr_manufacturer')->nullable();
            $table->text('gpsr_eu_responsible')->nullable();
            $table->text('gpsr_warnings')->nullable();
            $table->text('gpsr_manual')->nullable();
            $table->text('gpsr_class_category_danger')->nullable();
            $table->text('gpsr_class_languages')->nullable();
            $table->boolean('pictogram_toxic')->default(false);
            $table->boolean('pictogram_corrosive')->default(false);
            $table->boolean('pictogram_explosive')->default(false);
            $table->boolean('pictogram_flammable')->default(false);
            $table->boolean('pictogram_gas')->default(false);
            $table->boolean('pictogram_environment')->default(false);
            $table->boolean('pictogram_health')->default(false);
            $table->boolean('pictogram_oxidising')->default(false);
            $table->boolean('pictogram_danger')->default(false);
            $table->text('cpnp_number')->nullable()->index();
            $table->text('scpn_number')->nullable()->index();
            $table->text('ufi_number')->nullable()->index();
            $table->string('country_of_origin')->nullable()->index();
            $table->string('tariff_code')->nullable()->index();
            $table->string('duty_rate')->nullable()->index();
            $table->string('hts_us')->nullable()->index();
            $table->text('marketing_ingredients')->nullable();
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
            $table->dropColumn([
                'un_number',
                'un_class',
                'packing_group',
                'proper_shipping_name',
                'hazard_identification_number',
                'gpsr_manufacturer',
                'gpsr_eu_responsible',
                'gpsr_warnings',
                'gpsr_manual',
                'gpsr_class_category_danger',
                'gpsr_class_languages',
                'pictogram_toxic',
                'pictogram_corrosive',
                'pictogram_explosive',
                'pictogram_flammable',
                'pictogram_gas',
                'pictogram_environment',
                'pictogram_health',
                'pictogram_oxidising',
                'pictogram_danger',
                'cpnp_number',
                'scpn_number',
                'ufi_number',
                'country_of_origin',
                'tariff_code',
                'duty_rate',
                'hts_us',
                'marketing_ingredients',
            ]);
        });
    }
};
