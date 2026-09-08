<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class AssignArtefactsToFamiliesFromOrgStockFamilies
{
    use AsAction;

    public string $commandSignature = 'repair:artefact_families {--resync : Also re-point artefacts that already have a family} {--fix : Apply the changes, otherwise dry run}';

    /**
     * @return array{families_created: int, artefacts_assigned: int, no_department: int, no_org_stock_family: int}
     */
    public function handle(bool $fix = false, bool $resync = false): array
    {
        $familiesCreated   = 0;
        $artefactsAssigned = 0;
        $noDepartment      = 0;
        $noOrgStockFamily  = 0;

        $familyIdByKey  = [];
        $touchedFamilies = [];

        $query = Artefact::with(['orgStock', 'artefactDepartment'])->orderBy('id');
        if (!$resync) {
            $query->whereNull('artefact_family_id');
        }

        foreach ($query->cursor() as $artefact) {
            $orgStockFamilyId = $artefact->orgStock?->org_stock_family_id;

            if (!$orgStockFamilyId) {
                $noOrgStockFamily++;
                continue;
            }

            if (!$artefact->artefact_department_id) {
                $noDepartment++;
                continue;
            }

            $key = $artefact->artefact_department_id.':'.$orgStockFamilyId;

            if (!array_key_exists($key, $familyIdByKey)) {
                $family = ArtefactFamily::where('artefact_department_id', $artefact->artefact_department_id)
                    ->where('org_stock_family_id', $orgStockFamilyId)
                    ->first();

                if (!$family && $fix) {
                    $family = $this->createFamily($artefact, $orgStockFamilyId);
                }

                if (!$family) {
                    $familiesCreated++;
                    $artefactsAssigned++;
                    $familyIdByKey[$key] = null;
                    continue;
                }

                $familiesCreated += $family->wasRecentlyCreated ? 1 : 0;
                $familyIdByKey[$key] = $family->id;
            }

            $familyId = $familyIdByKey[$key];

            if ($familyId === null) {
                $artefactsAssigned++;
                continue;
            }

            if ($artefact->artefact_family_id == $familyId) {
                continue;
            }

            if ($fix) {
                $touchedFamilies[$familyId] = $familyId;
                if ($artefact->artefact_family_id) {
                    $touchedFamilies[$artefact->artefact_family_id] = $artefact->artefact_family_id;
                }
                $artefact->update(['artefact_family_id' => $familyId]);
            }

            $artefactsAssigned++;
        }

        foreach (ArtefactFamily::whereIn('id', $touchedFamilies)->get() as $artefactFamily) {
            ArtefactFamilyHydrateArtefacts::run($artefactFamily);
        }

        return [
            'families_created'    => $familiesCreated,
            'artefacts_assigned'  => $artefactsAssigned,
            'no_department'       => $noDepartment,
            'no_org_stock_family' => $noOrgStockFamily,
        ];
    }

    private function createFamily(Artefact $artefact, int $orgStockFamilyId): ArtefactFamily
    {
        $orgStockFamily = $artefact->orgStock->orgStockFamily;

        return StoreArtefactFamily::make()->action(
            $artefact->artefactDepartment,
            [
                'code'                => $this->availableCode($artefact->artefact_department_id, $orgStockFamily->code),
                'name'                => $orgStockFamily->name ?: $orgStockFamily->code,
                'org_stock_family_id' => $orgStockFamilyId,
            ]
        );
    }

    /* Two org stock families can carry the same code, but a code is unique inside a department. */
    private function availableCode(int $artefactDepartmentId, string $code): string
    {
        $code = Str::limit($code, 64, '');
        $candidate = $code;
        $suffix = 1;

        while (ArtefactFamily::where('artefact_department_id', $artefactDepartmentId)->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = Str::limit($code, 61, '').'-'.$suffix;
        }

        return $candidate;
    }

    public function asCommand(Command $command): int
    {
        $fix    = (bool)$command->option('fix');
        $resync = (bool)$command->option('resync');

        $result = $this->handle($fix, $resync);

        $command->info($fix ? 'Applied:' : 'Dry run, nothing written:');
        $command->table(
            ['families created', 'artefacts assigned', 'skipped: no department', 'skipped: no org stock family'],
            [[
                $result['families_created'],
                $result['artefacts_assigned'],
                $result['no_department'],
                $result['no_org_stock_family'],
            ]]
        );

        return 0;
    }
}
