<?php

namespace App\Services\HumanResources;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\HumanResources\LeaveType;

class LeaveTypeResolver
{
    public static function optionsForOrganisation(int $organisationId, bool $onlyActive = true, ?string $regionCode = null): array
    {
        return LeaveType::query()
            ->where('organisation_id', $organisationId)
            ->when($onlyActive, function ($query) {
                $query->where('is_active', true);
            })
            ->where(function ($query) use ($regionCode) {
                $query->whereNull('region_code')
                    ->when($regionCode, function ($query) use ($regionCode) {
                        $query->orWhere('region_code', $regionCode);
                    });
            })
            ->orderBy('name')
            ->get(['code', 'name', 'category'])
            ->mapWithKeys(function (LeaveType $leaveType) {
                return [
                    $leaveType->code => [
                        'label' => $leaveType->name,
                        'category' => $leaveType->category
                    ]
                ];
            })
            ->all();
    }

    public static function findForOrganisationByCode(int $organisationId, string $code, bool $onlyActive = true): ?LeaveType
    {
        return LeaveType::query()
            ->where('organisation_id', $organisationId)
            ->where('code', $code)
            ->when($onlyActive, function ($query) {
                $query->where('is_active', true);
            })
            ->first();
    }

    public static function bucketFromLeaveType(?LeaveType $leaveType, ?string $typeCode): string
    {
        if ($leaveType?->category) {
            return match ($leaveType->category) {
                LeaveCategoryEnum::ANNUAL => 'annual',
                LeaveCategoryEnum::MEDICAL => 'medical',
                LeaveCategoryEnum::PERSONAL => 'personal',
                LeaveCategoryEnum::SPECIAL => 'unpaid',
            };
        }

        return match ($typeCode) {
            'annual' => 'annual',
            'medical' => 'medical',
            default => 'unpaid',
        };
    }

    public static function isMedical(?LeaveType $leaveType, ?string $typeCode): bool
    {
        if ($leaveType?->category) {
            return $leaveType->category === LeaveCategoryEnum::MEDICAL;
        }

        return $typeCode === 'medical';
    }
}
