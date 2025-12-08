<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Facades\DB;

trait WithAuroraHumanResourcesParsers
{
    protected function parsePositions($userID): array
    {
        $rawJobPositions = $this->parseJobPositions();

        $shops = [];

        if ($userID) {
            $shops = $this->getAuroraUserShopScopes($userID);
        }

        $positions = [];
        foreach ($rawJobPositions as $jobPositionCode) {
            /** @var JobPosition $jobPosition */
            $jobPosition = $this->organisation->jobPositions()->where('code', $jobPositionCode)->firstOrFail();
            $scopes = [];
            $add = true;

            if ($jobPosition->scope == JobPositionScopeEnum::SHOPS) {
                $scopes = ['shops' => ['slug' => $shops]];
                if (count($shops) == 0) {
                    $add = false;
                }
            } elseif ($jobPosition->scope == JobPositionScopeEnum::WAREHOUSES) {
                $scopes = [
                    'warehouses' => [
                        'slug' => $this->organisation->warehouses()->pluck('slug')->all(),
                    ],
                ];
            } elseif ($jobPosition->scope == JobPositionScopeEnum::PRODUCTIONS) {
                $scopes = [
                    'productions' => [
                        'slug' => $this->organisation->productions()->pluck('slug')->all(),
                    ],
                ];
            } elseif ($jobPosition->scope == JobPositionScopeEnum::ORGANISATION) {
                $scopes = [
                    'organisations' => [
                        'slug' => [$this->organisation->slug],
                    ],

                ];
            } elseif ($jobPosition->scope == JobPositionScopeEnum::FULFILMENTS || $jobPosition->scope == JobPositionScopeEnum::FULFILMENTS_WAREHOUSES) {
                $scopes = [
                    'fulfilments' => [
                        'slug' => $this->organisation->fulfilments()->pluck('slug')->all(),
                    ],
                    'warehouses' => [
                        'slug' => $this->organisation->warehouses()->pluck('slug')->all(),
                    ],

                ];
            }

            if ($add) {
                $positions[] = [
                    'slug' => $jobPosition->slug,
                    'scopes' => $scopes,
                ];
            }
        }

        return $positions;
    }

    private function parseJobPositions(): array
    {
        $jobPositions = $this->organisation->jobPositions()->pluck('id', 'code')->all();

        $jobPositionCodes = [];

        $staffGroups = $this->auroraModelData->staff_groups;
        $staffGroups = $staffGroups === null ? '' : $staffGroups;

        foreach (explode(',', $staffGroups) as $sourceStaffGroups) {
            $jobPositionCode = $this->parseStaffGroups(
                isSupervisor: $this->auroraModelData->{'Staff Is Supervisor'} == 'Yes',
                staffGroupKey: $sourceStaffGroups
            );

            if ($jobPositionCode) {
                $jobPositionCodes = array_merge(
                    $jobPositionCodes,
                    explode(
                        ',',
                        $jobPositionCode
                    )
                );
            }
        }

        $staffPositions = $this->auroraModelData->staff_positions;
        $staffPositions = $staffPositions === null ? '' : $staffPositions;
        foreach (explode(',', $staffPositions) as $sourceStaffPosition) {
            $jobPositionCode = $this->parseJobPosition(
                isSupervisor: $this->auroraModelData->{'Staff Is Supervisor'} == 'Yes',
                sourceCode: $sourceStaffPosition
            );
            if ($jobPositionCode) {
                $jobPositionCodes = array_merge(
                    $jobPositionCodes,
                    explode(
                        ',',
                        $jobPositionCode
                    )
                );
            }
        }

        $jobPositionIds = [];

        foreach ($jobPositionCodes as $jobPositionCode) {
            if (array_key_exists($jobPositionCode, $jobPositions)) {
                $jobPositionIds[$jobPositions[$jobPositionCode]] = $jobPositionCode;
            }
        }

        return $jobPositionIds;
    }

    protected function parseStaffGroups($isSupervisor, $staffGroupKey): ?string
    {
        return match ((int) $staffGroupKey) {
            1 => 'org-admin',
            6 => 'hr-c',
            20 => 'hr-m',
            8, 21, 28 => 'buy',
            7 => 'prod-m',
            4 => 'prod-c',
            3 => 'wah-sc',
            22 => 'wah-m',
            23 => $isSupervisor ? 'acc-m' : 'acc-c',
            24 => 'dist-pik',
            25 => 'dist-pak',
            16 => 'cus-m',
            2 => 'cus-c',
            18 => 'shk-m',
            9, 26 => 'shk-c',
            30 => 'mkt-m',
            29 => 'mkt-c',
            32 => 'ful-m',
            default => null
        };
    }

    protected function parseJobPosition($isSupervisor, $sourceCode): string
    {
        return match ($sourceCode) {
            'WAHM' => 'wah-m',
            'WAHSK' => 'wah-sk',
            'WAHSC' => 'wah-sc',
            'PICK' => 'dist-pik,dist-pak',
            'OHADM' => 'dist-m',
            'PRODM' => 'prod-m',
            'PRODO' => 'prod-w',
            'CUSM' => 'cus-m',
            'CUS' => 'cus-c',
            'MRK' => $isSupervisor ? 'mrk-m' : 'mrk-c',
            'WEB' => $isSupervisor ? 'shk-m' : 'shk-c',
            'HR' => $isSupervisor ? 'hr-m' : 'hr-c',
            default => strtolower($sourceCode)
        };
    }

    protected function getAuroraUserShopScopes($userID): array
    {
        $shops = [];

        foreach (
            DB::connection('aurora')->table('User Right Scope Bridge')->where('User Key', $userID)->get() as $rawScope
        ) {
            if ($rawScope->{'Scope'} == 'Store') {
                $shop = $this->parseShop($this->organisation->id.':'.$rawScope->{'Scope Key'});
                $shops[$shop->slug] = true;
            }
            if ($rawScope->{'Scope'} == 'Website') {
                $auroraShopFromWebsiteData = DB::connection('aurora')->table('Website Dimension')->select('Website Store Key')->where('Website Key', $userID)->first();
                if ($auroraShopFromWebsiteData) {
                    $shop = $this->parseShop($this->organisation->id.':'.$auroraShopFromWebsiteData->{'Website Store Key'});
                    $shops[$shop->slug] = true;
                }
            }
        }

        return array_keys($shops);
    }
}
