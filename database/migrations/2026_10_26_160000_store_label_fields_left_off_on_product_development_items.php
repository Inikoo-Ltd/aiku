<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const array COMPLIANCE_FIELDS = ['eu_responsible', 'ingredients', 'manufacturer', 'ufi', 'un_number', 'warnings'];

    public function up(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->jsonb('label_excluded')->default('[]');
        });

        foreach (DB::table('product_development_items')->whereNotNull('label_fields')->get(['id', 'label_fields']) as $item) {
            $included = json_decode($item->label_fields, true) ?: [];
            DB::table('product_development_items')->where('id', $item->id)->update([
                'label_excluded' => json_encode(array_values(array_diff(self::COMPLIANCE_FIELDS, $included))),
            ]);
        }

        Schema::table('product_development_items', function (Blueprint $table) {
            $table->dropColumn('label_fields');
        });
    }

    public function down(): void
    {
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->jsonb('label_fields')->nullable();
        });
        Schema::table('product_development_items', function (Blueprint $table) {
            $table->dropColumn('label_excluded');
        });
    }
};
