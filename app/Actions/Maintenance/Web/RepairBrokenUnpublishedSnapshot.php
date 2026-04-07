<?php

/*
 * author Louis Perez
 * created on 07-04-2026-13h-32m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairBrokenUnpublishedSnapshot
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Website $website, Command $command): void
    {
        
        if ($liveSnapshot = $website->liveSubDepartmentSnapshot) {
            $command->info('~ Repairing: unpublished sub_department web block: From Live');
            $unpublishedSnapshot = $website->unpublishedSubDepartmentSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'sub_department'  => $liveSnapshot->layout
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::SUB_DEPARTMENT,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'sub_department'  => $liveSnapshot->layout
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_sub_department_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        } else {
            $command->info('~ Repairing: unpublished sub_department web block: Set Null');
            $unpublishedSnapshot = $website->unpublishedSubDepartmentSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'sub_department'  => []
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::SUB_DEPARTMENT,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'sub_department'  => []
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_sub_department_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        };

        if ($liveSnapshot = $website->liveFamilySnapshot) {
            $command->info('~ Repairing: unpublished family web block: From Live');
            $unpublishedSnapshot = $website->unpublishedFamilySnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'family'  => $liveSnapshot->layout
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::FAMILY,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'family'  => $liveSnapshot->layout
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_family_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        } else {
            $command->info('~ Repairing: unpublished family web block: Set Null');
            $unpublishedSnapshot = $website->unpublishedFamilySnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'family'  => []
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::FAMILY,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'family'  => []
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_family_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        };
        
        if ($liveSnapshot = $website->liveFamiliesOverviewSnapshot) {
            $command->info('~ Repairing: unpublished families_overview web block: From Live');
            $unpublishedSnapshot = $website->unpublishedFamiliesOverviewSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'families_overview'  => $liveSnapshot->layout
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::FAMILIES_OVERVIEW,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'families_overview'  => $liveSnapshot->layout
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_families_overview_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        } else {
            $command->info('~ Repairing: unpublished families_overview web block: Set Null');
            $unpublishedSnapshot = $website->unpublishedFamiliesOverviewSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'families_overview'  => []
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::FAMILIES_OVERVIEW,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'families_overview'  => []
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_families_overview_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        };
        
        if ($liveSnapshot = $website->liveProductsSnapshot) {
            $command->info('~ Repairing: unpublished products web block: From Live');
            $unpublishedSnapshot = $website->unpublishedProductsSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'products'  => $liveSnapshot->layout
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::PRODUCTS,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'products'  => $liveSnapshot->layout
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_products_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        } else {
            $command->info('~ Repairing: unpublished products web block: Set Null');
            $unpublishedSnapshot = $website->unpublishedProductsSnapshot;

            if ($unpublishedSnapshot) {
                $unpublishedSnapshot->updateQuietly([
                    'layout'    => [
                        'products'  => []
                    ]
                ]);
            } else {
                $unpublishedSnapshot = StoreWebsiteSnapshot::run(
                    $website,
                    [
                        'scope'  => SnapshotScopeEnum::PRODUCTS,
                        'publisher_id'   => 1,
                        'publisher_type' => 'User',
                        'layout' => [
                            'products'  => []
                        ]
                    ]
                );

                $website->updateQuietly([
                    'unpublished_products_snapshot_id'  => $unpublishedSnapshot->id
                ]);
            }
        };

        $command->info('==== DONE ====');
    }

    public string $commandSignature = 'repair:broken_unpublished_snapshot {website_id?}';

    public function asCommand(Command $command): void
    {
        // dd($command->argument('website_id'));
        $websites = Website::when($command->argument('website_id'), 
                function ($query) use ($command) {
                    $query->where('id', $command->argument('website_id'));
                }, 
            )
            ->where('status', true);

        $total   = $websites->clone()->count();

        $current = 1;

        foreach ($websites->get() as $website) {
            $command->info("\nPROGRESS: [{$current}/{$total}] Website id: {$website->id}\n");
            $this->handle($website, $command);
            $current++;
        }
    }

}
