<?php

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * In-house developments have no agent and buy nothing from a supplier: their product specs go to the
 * production team, and the commercial terms, the supplier product fields and the factory photos are dropped.
 */
return new class () extends Migration {
    public function up(): void
    {
        $developmentIds = DB::table('product_developments')->where('kind', 'recipe')->pluck('id');
        if ($developmentIds->isEmpty()) {
            return;
        }

        $supplierProductFields = collect(ProductDevelopmentTargetFieldEnum::cases())
            ->filter(fn (ProductDevelopmentTargetFieldEnum $field) => $field->isOnSupplierProduct())
            ->map(fn (ProductDevelopmentTargetFieldEnum $field) => $field->value)
            ->values()
            ->all();

        DB::table('product_development_checklist_items')
            ->whereIn('product_development_id', $developmentIds)
            ->where(fn ($query) => $query->where('role', 'purchasing')
                ->orWhere('key', 'factory_photos')
                ->orWhereIn('target_field', $supplierProductFields))
            ->delete();

        DB::table('product_development_checklist_items')
            ->whereIn('product_development_id', $developmentIds)
            ->where('role', 'agent')
            ->update(['role' => 'production']);

        DB::table('product_development_stages')
            ->whereIn('product_development_id', $developmentIds)
            ->where('stage', 'commercial_terms')
            ->delete();

        DB::table('product_development_stages')
            ->whereIn('product_development_id', $developmentIds)
            ->where('stage', 'product_specs')
            ->update(['role' => 'production']);

        foreach (ProductDevelopment::whereIn('id', $developmentIds)->get() as $productDevelopment) {
            ProductDevelopmentHydrateChecklist::run($productDevelopment);
        }
    }

    public function down(): void
    {
    }
};
