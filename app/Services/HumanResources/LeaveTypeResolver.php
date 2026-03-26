<?php

namespace App\Services\HumanResources;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\HumanResources\LeaveType;

class LeaveTypeResolver
{
    protected const BUCKET_CODE_MAP = [
        'annual' => 'annual',
        'holiday' => 'annual',
        'holiday-vacation' => 'annual',
        'halfday-morning' => 'annual',
        'halfday-afternoon' => 'annual',
        'medical' => 'medical',
        'sick-leave' => 'medical',
        'unpaid' => 'unpaid',
        'unpaid-leave' => 'unpaid',
    ];

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
        $normalizedTypeCode = $typeCode ? strtolower($typeCode) : null;

        if ($normalizedTypeCode && array_key_exists($normalizedTypeCode, self::BUCKET_CODE_MAP)) {
            return self::BUCKET_CODE_MAP[$normalizedTypeCode];
        }

        $leaveTypeCode = $leaveType?->code ? strtolower($leaveType->code) : null;
        if ($leaveTypeCode && array_key_exists($leaveTypeCode, self::BUCKET_CODE_MAP)) {
            return self::BUCKET_CODE_MAP[$leaveTypeCode];
        }

        if ($leaveType?->category) {
            return match ($leaveType->category) {
                LeaveCategoryEnum::ANNUAL => 'annual',
                LeaveCategoryEnum::MEDICAL => 'medical',
                LeaveCategoryEnum::PERSONAL => 'personal',
                LeaveCategoryEnum::SPECIAL => 'special',
                LeaveCategoryEnum::UNPAID => 'unpaid',
            };
        }

        return match ($normalizedTypeCode) {
            'special' => 'special',
            'personal' => 'personal',
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
