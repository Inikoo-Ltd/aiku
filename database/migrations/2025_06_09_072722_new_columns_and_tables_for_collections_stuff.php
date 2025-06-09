<?php

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;
    public function up(): void
    {
        Schema::create('webpage_has_collections', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedInteger('webpage_id')->index();
            $table->foreign('webpage_id')->references('id')->on('webpages');
            $table->unsignedInteger('collection_id')->index();
            $table->foreign('collection_id')->references('id')->on('collections');
            $table->timestampsTz();
        });

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_parent_webpages')->default(0);
            $table->unsignedInteger('number_sub_departments')->default(0);
            $table->unsignedSmallInteger('number_current_sub_departments')->default(0)->comment('state: active+discontinuing');
            foreach (ProductCategoryStateEnum::cases() as $subDepartmentState) {
                $table->unsignedInteger('number_sub_departments_state_'.$subDepartmentState->snake())->default(0);
            }
        });

        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_collections')->default(0);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('webpage_has_collections');

        Schema::table('collection_stats', function (Blueprint $table) {
            $table->dropColumn('number_parent_webpages');
            $table->dropColumn('number_sub_departments');
            $table->dropColumn('number_current_sub_departments');
            foreach (ProductCategoryStateEnum::cases() as $case) {
                $table->dropColumn("number_sub_departments_state_{$case->snake()}");
            }
        });

        Schema::table('webpage_stats', function (Blueprint $table) {
            $table->dropColumn('number_collections');
        });
    }
};
