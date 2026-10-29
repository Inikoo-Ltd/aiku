<?php

namespace Tests\Feature;

use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Models\HumanResources\Employee;
use App\Actions\Production\JobOrder\ConfirmJobOrder;
use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\ManufactureTask\StoreManufactureTask;
use App\Models\Production\ArtefactManufactureTask;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardAllowanceTypeEnum;
use App\Enums\Production\ManufactureTask\ManufactureTaskOperativeRewardTermsEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;

use function Pest\Laravel\patch;

function stepTestManufactureTask(Production $production, string $code = 'BOX', string $name = 'Boxing'): ManufactureTask
{
    return ManufactureTask::where('production_id', $production->id)->where('code', $code)->first()
        ?? StoreManufactureTask::make()->action($production, [
            'code'                            => $code,
            'name'                            => $name,
            'task_materials_cost'             => 1,
            'task_energy_cost'                => 1,
            'task_other_cost'                 => 1,
            'task_work_cost'                  => 1,
            'task_lower_target'               => 1,
            'task_upper_target'               => 1,
            'operative_reward_terms'          => ManufactureTaskOperativeRewardTermsEnum::ABOVE_LOWER_LIMIT->value,
            'operative_reward_allowance_type' => ManufactureTaskOperativeRewardAllowanceTypeEnum::OFFSET_SALARY->value,
            'operative_reward_amount'         => 1,
        ]);
}

function stepTestSecondOperator(\App\Models\SysAdmin\Group $group): \App\Models\SysAdmin\User
{
    return \App\Actions\SysAdmin\Guest\StoreGuest::make()->action(
        $group,
        array_merge(\App\Models\SysAdmin\Guest::factory()->definition(), ['positions' => [['slug' => 'group-admin', 'scopes' => []]]])
    )->getUser();
}

function unifiedRecipeSteps(Artefact $artefact): array
{
    return ArtefactManufactureTask::where('artefact_id', $artefact->id)
        ->orderBy('position')
        ->get()
        ->map(fn (ArtefactManufactureTask $step) => [
            'manufacture_task_id' => $step->manufacture_task_id,
            'position'            => (int) $step->position,
            'units_per_artefact'  => (float) $step->units_per_artefact,
            'raw_materials'       => $step->rawMaterials()->pluck('quantity_per_unit', 'raw_material_id')->map(fn ($quantity) => (float) $quantity)->all(),
        ])
        ->all();
}

function labelTestOrgStock(\App\Models\SysAdmin\Organisation $organisation, \App\Models\SysAdmin\Group $group): \App\Models\Inventory\OrgStock
{
    $stock = \App\Actions\Goods\Stock\StoreStock::make()->action(
        $group,
        array_merge(\App\Models\Goods\Stock::factory()->definition(), [
            'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE
        ])
    );

    return \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($organisation, $stock);
}

function dayPlanOperator(\App\Models\SysAdmin\Organisation $organisation): Employee
{
    $modelData = Employee::factory()->make(['organisation_id' => $organisation->id])->toArray();
    $modelData['worker_number']   = 'W'.rand(10000, 99999);
    $modelData['alias']           = 'Alias '.rand(10000, 99999);
    $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
    $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
    $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

    return StoreEmployee::make()->action($organisation, $modelData);
}

function stepOwnerFixture(object $test): array
{
    [$artisanA, $artisanB] = collect(range(1, 2))->map(function () use ($test) {
        $modelData = Employee::factory()->make(['organisation_id' => $test->organisation->id])->toArray();
        $modelData['worker_number']   = 'W'.rand(1000, 9999);
        $modelData['alias']           = 'Alias '.rand(1000, 9999);
        $modelData['type']            = \App\Enums\HumanResources\Employee\EmployeeTypeEnum::EMPLOYEE;
        $modelData['employment_type'] = \App\Enums\HumanResources\Employee\EmploymentTypeEnum::FULL_TIME;
        $modelData['state']           = \App\Enums\HumanResources\Employee\EmployeeStateEnum::WORKING;

        return StoreEmployee::make()->action($test->organisation, $modelData);
    })->all();

    $suffix  = rand(10000, 99999);
    $mixTask = StoreManufactureTask::make()->action($test->production, [
        'code'              => 'MIX'.$suffix,
        'name'              => 'Mixing '.$suffix,
        'is_piece_rate'     => false,
        'is_recorded_in_kg' => true,
    ]);
    $packingTask = StoreManufactureTask::make()->action($test->production, [
        'code'          => 'PCK'.$suffix,
        'name'          => 'Packing '.$suffix,
        'is_piece_rate' => false,
    ]);
    $artefact = StoreArtefact::make()->action($test->production, ['state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value, 'code' => 'OWN'.$suffix, 'name' => 'Owner '.$suffix]);
    $artefact->manufactureTasks()->sync([
        $mixTask->id                => ['position' => 1, 'units_per_artefact' => 0.1],
        $test->manufactureTask->id  => ['position' => 2, 'units_per_artefact' => 1],
        $packingTask->id            => ['position' => 3, 'units_per_artefact' => 1],
    ]);

    $jobOrder = StoreJobOrder::make()->action($test->production, []);
    $line     = StoreJobOrderItem::make()->action($jobOrder, ['artefact_id' => $artefact->id, 'quantity' => 1344]);
    ConfirmJobOrder::make()->action($jobOrder);

    $splitUrl = route('grp.models.job-order-item.split', ['jobOrderItem' => $line->id]);
    patch($splitUrl, ['assignments' => [['id' => $line->id, 'employee_id' => $artisanA->id, 'quantity' => 1344]]])->assertSessionHasNoErrors();

    $tasks = $line->tasks()->orderBy('position')->get();

    return [$artisanA, $artisanB, $jobOrder, $line, $tasks, $splitUrl];
}
