<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\HumanResources\Employee;
use Illuminate\Validation\Rule;

trait WithProductDevelopmentFormulationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function formulationRules(ProductDevelopment $productDevelopment, bool $nameRequired): array
    {
        return [
            'name'       => [$nameRequired ? 'required' : 'sometimes', 'string', 'max:120'],
            'artisan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $productDevelopment->production?->organisation_id ?? 0)],
        ];
    }

    /**
     * The working artisans of the development's factory, to propose one for a formulation stage.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function artisanOptions(ProductDevelopment $productDevelopment): array
    {
        $organisationId = $productDevelopment->production?->organisation_id;
        if (!$organisationId) {
            return [];
        }

        return Employee::where('organisation_id', $organisationId)
            ->where('state', EmployeeStateEnum::WORKING)
            ->orderBy('contact_name')
            ->get(['id', 'contact_name'])
            ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])
            ->all();
    }
}
