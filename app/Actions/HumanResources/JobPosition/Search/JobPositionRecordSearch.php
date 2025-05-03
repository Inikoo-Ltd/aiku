<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\HumanResources\JobPosition\Search;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Helpers\UniversalSearch;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\Concerns\AsAction;

class JobPositionRecordSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(JobPosition $jobPosition): void
    {

        if (!$jobPosition->organisation) {
            $organisationShops = Organisation::where("type", OrganisationTypeEnum::SHOP->value)->get();
            foreach ($organisationShops as $organisation) {
                $this->saveToUniversalSearch($jobPosition, $organisation);
            }
        } else {
            $this->saveToUniversalSearch($jobPosition, $jobPosition->organisation);
        }
    }

    private function saveToUniversalSearch(JobPosition $jobPosition, Organisation $organisation): void
    {

        $modelData = [
            'group_id'          => $jobPosition->group_id,
            'organisation_id'   => $organisation->id,
            'organisation_slug' => $organisation->slug,
            'sections'          => ['hr'],
            'haystack_tier_1'   => trim($jobPosition->name . ' ' . $jobPosition->code),
            'result'            => [
            'route'      => [
                'name'       => 'grp.org.hr.job_positions.show',
                'parameters' => [
                    'organisation' => $organisation->slug,
                    'jobPosition'  => $jobPosition->slug,
                ]
            ],
            'code' => [
                'label' => $jobPosition->name,
            ],
            'icon'       => [
                'icon' => 'fal fa-clipboard-list-check'
            ],
            'meta'       => [
                    $jobPosition->stats->number_employees_currently_working > 0 ?
                    [
                        'type'   => 'number',
                        'label' => __('employees') . ": ",
                        'number'   => $jobPosition->stats->number_employees_currently_working,
                    ] : [],
                    $jobPosition->stats->number_guests_status_active > 0 ?
                    [
                        'type'   => 'number',
                        'label' => __('guests') . ": ",
                        'number'   => $jobPosition->stats->number_guests_status_active,
                    ] : [],
                ]
            ]
        ];

        $oldEntries = UniversalSearch::where('model_id', $jobPosition->id)
        ->where('model_type', class_basename(JobPosition::class))
        ->where('organisation_id', $organisation->id)
        ->get();

        if ($oldEntries->isNotEmpty()) {
            $oldEntries->each(function ($entry) {
                $entry->delete();
            });
        }

        UniversalSearch::create(array_merge($modelData, [
            'model_id'   => $jobPosition->id,
            'model_type' => class_basename(JobPosition::class),
        ]));

    }


}
