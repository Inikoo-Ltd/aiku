<?php

/*
 * Author: Andiferdiawan <andiferdiawan@gmail.com>
 * Created: 2026
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\GrpAction;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SyncMasterDepartmentFamilyOrdering extends GrpAction
{
    public function handle(MasterProductCategory $masterDepartment, array $modelData): void
    {
        $positionMap = $modelData['family_position_map'];

        $now = now();

        foreach ($positionMap as $zeroBasedPosition => $familyId) {
            DB::table('master_department_family_orderings')
                ->where('master_department_id', $masterDepartment->id)
                ->where('master_family_id', (int) $familyId)
                ->update([
                    'position'   => (int) $zeroBasedPosition + 1,
                    'updated_at' => $now,
                ]);
        }
    }

    public function rules(): array
    {
        return [
            'family_position_map'   => ['required', 'array'],
            'family_position_map.*' => ['integer', Rule::exists('master_product_categories', 'id')],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {
        $this->initialisation(group(), $request);

        $this->handle($masterProductCategory, $this->validatedData);
    }
}
