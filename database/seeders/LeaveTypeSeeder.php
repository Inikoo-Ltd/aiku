<?php

namespace Database\Seeders;

use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\Models\HumanResources\LeaveType;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run()
    {
        $globalLeaveTypes = [
            [
                'code' => 'birthday',
                'name' => 'Birthday',
                'color' => 'pink',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Birthday leave',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'marriage',
                'name' => 'Marriage Leave',
                'color' => 'white',
                'category' => LeaveCategoryEnum::SPECIAL,
                'description' => 'Bereavement Leave',
                'requires_approval' => true,
                'max_days_per_year' => 0,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'hd-morning',
                'name' => 'Halfday Morning',
                'color' => 'yellow',
                'category' => LeaveCategoryEnum::ANNUAL,
                'description' => 'Half Day Morning Leave',
                'requires_approval' => false,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'hd-afternoon',
                'name' => 'Halfday Afternoon',
                'color' => 'indigo',
                'category' => LeaveCategoryEnum::ANNUAL,
                'description' => 'Half Day Afternoon Leave',
                'requires_approval' => false,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            ['code' => 'holiday',
                'name' => 'Holiday',
                'color' => 'green',
                'category' => LeaveCategoryEnum::ANNUAL,
                'description' => 'Public holiday',
                'requires_approval' => false,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            ['code' => 'holiday-vacation',
                'name' => 'Holiday / Vacation',
                'color' => 'green',
                'category' => LeaveCategoryEnum::ANNUAL,
                'description' => 'Holiday or vacation time',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'late-for-work',
                'name' => 'Late for Work',
                'color' => 'orange',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Late for work attendance',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'meeting',
                'name' => 'Meeting',
                'color' => 'blue',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Meeting attendance',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'out-of-office',
                'name' => 'Out of Office (Work Related)',
                'color' => 'gray',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Out of office for work-related activities',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'parental-maternity-paternity',
                'name' => 'Parental (Maternity/Paternity)',
                'color' => 'cyan',
                'category' => LeaveCategoryEnum::SPECIAL,
                'description' => 'Maternity or paternity leave',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],

            [
                'code' => 'sick-leave',
                'name' => 'Sick Leave',
                'color' => 'red',
                'category' => LeaveCategoryEnum::MEDICAL,
                'description' => 'Sick leave for illness',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],
            [
                'code' => 'training',
                'name' => 'Training',
                'color' => 'purple',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Training or professional development',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],
            [
                'code' => 'unpaid-leave',
                'name' => 'Un-Paid Leave',
                'color' => 'black',
                'category' => LeaveCategoryEnum::SPECIAL,
                'description' => 'Unpaid leave',
                'requires_approval' => true,
                'max_days_per_year' => 14,
                'is_active' => true,
                'region_code' => null,
            ],
        ];

        $slovakiaLeaveTypes = [
            [
                'code' => 'nahradne-volno',
                'name' => 'Nahradne Volno',
                'color' => 'purple',
                'category' => LeaveCategoryEnum::SPECIAL,
                'description' => 'Replacement time off',
                'requires_approval' => true,
                'is_active' => true,
                'region_code' => 'SK',
            ],
            [
                'code' => 'ospravedlnena-nepritomnost',
                'name' => 'Ospravedlnena Nepritomnost',
                'color' => 'orange',
                'category' => LeaveCategoryEnum::PERSONAL,
                'description' => 'Justified absence',
                'requires_approval' => true,
                'is_active' => true,
                'region_code' => 'SK',
            ],
            [
                'code' => 'family-accompaniment',
                'name' => 'Sprevadzanie Rodinneho Prislusnika',
                'color' => 'cyan',
                'category' => LeaveCategoryEnum::ANNUAL,
                'description' => 'Accompaniment of a family member',
                'requires_approval' => true,
                'is_active' => true,
                'region_code' => 'SK',
            ],
        ];

        $organisations = Organisation::all();

        foreach ($organisations as $organisation) {
            $groupId = $organisation->group_id;

            foreach ($globalLeaveTypes as $leaveType) {
                LeaveType::updateOrCreate(
                    [
                        'organisation_id' => $organisation->id,
                        'code' => $leaveType['code'],
                        'region_code' => null,
                    ],
                    array_merge(
                        $leaveType,
                        [
                            'group_id' => $groupId,
                            'organisation_id' => $organisation->id,
                        ]
                    )
                );
            }

            if ($organisation->country_id) {
                $country = $organisation->country;

                if ($country && $country->code === 'SK') {
                    foreach ($slovakiaLeaveTypes as $leaveType) {
                        LeaveType::updateOrCreate(
                            [
                                'organisation_id' => $organisation->id,
                                'code' => $leaveType['code'],
                                'region_code' => 'SK',
                            ],
                            array_merge(
                                $leaveType,
                                [
                                    'group_id' => $groupId,
                                    'organisation_id' => $organisation->id,
                                ]
                            )
                        );
                    }
                }
            }
        }
    }
}
