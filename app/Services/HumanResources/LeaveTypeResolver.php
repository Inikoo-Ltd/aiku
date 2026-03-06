<?php

namespace App\Services\HumanResources;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\HumanResources\LeaveType;

class LeaveTypeResolver
{
    public static function optionsForOrganisation(int $organisationId, bool $onlyActive = true): array
    {
        return LeaveType::query()
            ->where('organisation_id', $organisationId)
            ->when($onlyActive, function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (LeaveType $leaveType) => [$leaveType->code => $leaveType->name])
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
                LeaveCategoryEnum::STATUTORY => 'annual',
                LeaveCategoryEnum::MEDICAL => 'medical',
                LeaveCategoryEnum::PERSONAL,
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
