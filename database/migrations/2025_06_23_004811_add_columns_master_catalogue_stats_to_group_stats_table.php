<?php

use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Stubs\Migrations\HasCatalogueStats;
use App\Stubs\Migrations\HasGoodsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasCatalogueStats;
    use HasGoodsStats;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('number_master_shops')->default(0);
            $table->unsignedSmallInteger('number_current_master_shops')->default(0)->comment('status=true');

            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->unsignedSmallInteger('number_master_shops_type_'.$shopType->snake())->default(0);
            }

            $table->unsignedSmallInteger('number_master_collections')->default(0);
            $table->unsignedSmallInteger('number_current_master_collections')->default(0)->comment('status=true');

            $table = $this->masterProductCategoriesStatsFields($table);
            $table = $this->masterAssetsStatsFields($table);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_stats', function (Blueprint $table) {

            $table->dropColumn([
                'number_master_collections',
                'number_current_master_collections'
            ]);

            $table->dropColumn([
                'number_master_shops',
                'number_current_master_shops'
            ]);

            foreach (ShopTypeEnum::cases() as $shopType) {
                $table->dropColumn('number_master_shops_type_'.$shopType->snake());
            }

            $table->dropColumn([
                'number_master_product_categories',
                'number_current_master_product_categories'
            ]);

            foreach (MasterProductCategoryTypeEnum::cases() as $case) {
                $table->dropColumn([
                    'number_master_product_categories_type_'.$case->snake(),
                    'number_current_master_product_categories_type_'.$case->snake()
                ]);
            }

            $table->dropColumn([
                'number_master_assets',
                'number_current_master_assets'
            ]);

            foreach (AssetTypeEnum::cases() as $case) {
                $table->dropColumn([
                    'number_master_assets_type_'.$case->snake(),
                    'number_current_master_assets_type_'.$case->snake()
                ]);
            }
        });
    }
};
