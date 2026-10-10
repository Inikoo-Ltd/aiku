<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('packaging_components', function (Blueprint $table) {
            $table->string('polymer', 16)->nullable();
            $table->boolean('is_composite')->default(false);
            $table->string('dominant_material', 32)->nullable();
            $table->string('colour', 32)->nullable();
            $table->boolean('is_reusable')->default(false);
            $table->boolean('is_beverage_container')->default(false);
            $table->unsignedInteger('capacity_ml')->nullable();
            $table->unsignedSmallInteger('carrier_bag_thickness_um')->nullable();
            $table->string('ram_rating', 8)->nullable();
            $table->date('ram_assessed_at')->nullable();
            $table->text('ram_evidence_ref')->nullable();
            $table->decimal('label_coverage_pct', 5, 2)->nullable();
            $table->boolean('has_carbon_black')->nullable();
            $table->boolean('is_laminated')->nullable();
        });

        Schema::table('packaging_families', function (Blueprint $table) {
            $table->string('brand_ownership', 16)->default('unknown')->index();
            $table->string('end_use', 16)->default('household');
            $table->boolean('is_product_itself')->default(false);
            $table->string('source', 32)->nullable()->index();
            $table->date('verified_at')->nullable();
        });

        Schema::create('epr_material_mappings', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('scheme', 8);
            $table->string('material_category', 32);
            $table->string('polymer', 16)->nullable();
            $table->string('scheme_material');
            $table->string('scheme_subcategory')->nullable();
            $table->timestampsTz();
            $table->unique(['scheme', 'material_category', 'polymer'])->nullsNotDistinct();
        });

        DB::table('epr_material_mappings')->insert($this->mappings());
    }

    public function down(): void
    {
        Schema::dropIfExists('epr_material_mappings');
        Schema::table('packaging_families', function (Blueprint $table) {
            $table->dropColumn(['brand_ownership', 'end_use', 'is_product_itself', 'source', 'verified_at']);
        });
        Schema::table('packaging_components', function (Blueprint $table) {
            $table->dropColumn([
                'polymer', 'is_composite', 'dominant_material', 'colour', 'is_reusable', 'is_beverage_container', 'capacity_ml',
                'carrier_bag_thickness_um', 'ram_rating', 'ram_assessed_at', 'ram_evidence_ref', 'label_coverage_pct', 'has_carbon_black', 'is_laminated',
            ]);
        });
    }

    private function mappings(): array
    {
        $byCategory = [
            'uk' => [
                'paper_cardboard' => ['Paper or card'],
                'composite'       => ['Fibre-based composite'],
                'plastic'         => ['Plastic'],
                'glass'           => ['Glass'],
                'aluminium'       => ['Aluminium'],
                'steel'           => ['Steel'],
                'wood'            => ['Wood'],
                'textile'         => ['Other'],
                'other'           => ['Other'],
            ],
            'sk' => [
                'paper_cardboard' => ['Papier, lepenka'],
                'composite'       => ['Nápojový kartón'],
                'plastic'         => ['Plasty', 'PET, HDPE, LDPE, PP, PS'],
                'glass'           => ['Sklo'],
                'aluminium'       => ['Kovy', 'Hliník'],
                'steel'           => ['Kovy', 'Železné'],
                'wood'            => ['Drevo'],
                'textile'         => ['Ostatné'],
                'other'           => ['Ostatné'],
            ],
            'de' => [
                'paper_cardboard' => ['PPK'],
                'composite'       => ['Getränkekarton'],
                'plastic'         => ['Kunststoff'],
                'glass'           => ['Glas'],
                'aluminium'       => ['Aluminium'],
                'steel'           => ['Eisenmetalle'],
                'wood'            => ['Sonstige Materialien'],
                'textile'         => ['Sonstige Materialien'],
                'other'           => ['Sonstige Materialien'],
            ],
            'es' => [
                'paper_cardboard' => ['Papel/cartón'],
                'composite'       => ['Brik'],
                'plastic'         => ['Plástico'],
                'glass'           => ['Vidrio'],
                'aluminium'       => ['Aluminio'],
                'steel'           => ['Acero'],
                'wood'            => ['Madera'],
                'textile'         => ['Otros'],
                'other'           => ['Otros'],
            ],
            'fr' => [
                'paper_cardboard' => ['Papier-carton'],
                'composite'       => ['Brique'],
                'plastic'         => ['Plastique'],
                'glass'           => ['Verre'],
                'aluminium'       => ['Aluminium'],
                'steel'           => ['Acier'],
                'wood'            => ['Bois'],
                'textile'         => ['Autres'],
                'other'           => ['Autres'],
            ],
        ];

        $byPolymer = [
            'sk' => ['eps' => 'EPS', 'pvc' => 'PVC', 'bio' => 'Ostatné plasty', 'other' => 'Ostatné plasty'],
        ];
        foreach (['es' => 'Plástico', 'fr' => 'Plastique'] as $scheme => $plastic) {
            foreach (['pet' => 'PET', 'hdpe' => 'PEHD', 'ldpe' => 'PEBD', 'pp' => 'PP', 'ps' => 'PS', 'eps' => 'PSE', 'pvc' => 'PVC', 'bio' => 'Bio', 'other' => 'Otros'] as $polymer => $resin) {
                $byPolymer[$scheme][$polymer] = $resin;
            }
        }

        $now  = now();
        $rows = [];
        foreach ($byCategory as $scheme => $categories) {
            foreach ($categories as $category => $names) {
                $rows[] = [
                    'scheme' => $scheme, 'material_category' => $category, 'polymer' => null,
                    'scheme_material' => $names[0], 'scheme_subcategory' => $names[1] ?? null, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach ($byPolymer[$scheme] ?? [] as $polymer => $subcategory) {
                $rows[] = [
                    'scheme' => $scheme, 'material_category' => 'plastic', 'polymer' => $polymer,
                    'scheme_material' => $byCategory[$scheme]['plastic'][0], 'scheme_subcategory' => $subcategory, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }
};
