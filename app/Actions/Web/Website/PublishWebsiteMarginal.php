<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 10:05:33 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Web\UpdateWebBlockToWebsiteAndChild;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\UpdateDescriptionBlockToWebsiteAndChild;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Website;
use App\Models\Web\WebBlockType;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class PublishWebsiteMarginal extends OrgAction
{
    use WithActionUpdate;

    public bool $isAction = false;
    public string $marginal;

    public function handle(Website $website, string $marginal, array $modelData): Website
    {
        $this->marginal = $marginal;
        $layout         = Arr::get($modelData, 'layout', []);
        $customAudit    = false;
        if ($marginal == 'header') {
            $oldLayout = $website->liveHeaderSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedHeaderSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedHeaderSnapshot?->layout, $marginal);
        } elseif ($marginal == 'footer') {
            $oldLayout = $website->liveFooterSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedFooterSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedFooterSnapshot?->layout, $marginal);
        } elseif ($marginal == 'menu') {
            $oldLayout = $website->liveMenuSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedMenuSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedMenuSnapshot?->layout, $marginal);
        } elseif ($marginal == 'sidebar') {
            $oldLayout = $website->liveSidebarSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedSidebarSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedSidebarSnapshot?->layout, $marginal);
        } elseif ($marginal == 'department') {
            $oldLayout = $website->liveDepartmentSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedDepartmentSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedDepartmentSnapshot?->layout, $marginal);
        } elseif ($marginal == 'sub_department') {
            $customAudit = true;
            $oldLayout   = $website->liveSubDepartmentSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedSubDepartmentSnapshot?->layout, $marginal);
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedSubDepartmentSnapshot?->layout, $marginal);
        } elseif ($marginal == 'family') {
            $customAudit = true;
            $oldLayout   = $website->liveFamilySnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedFamilySnapshot?->layout, $marginal);
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedFamilySnapshot?->layout, $marginal);
        } elseif ($marginal == 'families_overview') {
            $customAudit = true;
            $oldLayout   = $website->liveFamiliesOverviewSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedFamiliesOverviewSnapshot?->layout, $marginal);
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedFamiliesOverviewSnapshot?->layout, $marginal);
        } elseif ($marginal == 'family_description') {
            $customAudit = true;
            $oldLayout   = $website->liveFamilyDescriptionSnapshot?->layout ?? $website->unpublishedFamilyDescriptionSnapshot?->layout;
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedFamilyDescriptionSnapshot?->layout, $marginal);
        } elseif ($marginal == 'product') {
            $customAudit = true;
            $oldLayout   = $website->liveProductSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedProductSnapshot?->layout, $marginal);
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedProductSnapshot?->layout, $marginal);
        } elseif ($marginal == 'products') {
            $customAudit = true;
            $oldLayout   = $website->liveProductsSnapshot?->layout ?? Arr::get($website->unpublishedProductsSnapshot?->layout, $marginal);
            $layout      = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedProductsSnapshot?->layout, $marginal);
        } elseif ($marginal == 'collection') {
            $oldLayout = $website->liveCollectionSnapshot?->layout[$marginal] ?? Arr::get($website->unpublishedCollectionSnapshot?->layout, $marginal);
            $layout    = Arr::get($modelData, 'layout') ?? Arr::get($website->unpublishedCollectionSnapshot?->layout, $marginal);
        }

        $firstCommit = true;

        foreach ($website->snapshots()->where('scope', $marginal)->where('state', SnapshotStateEnum::LIVE)->get() as $liveSnapshot) {
            $firstCommit = false;
            UpdateSnapshot::run($liveSnapshot, [
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now()
            ]);
        }

        /** @var Snapshot $snapshot */
        $snapshot = StoreWebsiteSnapshot::run(
            $website,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $layout,
                'scope'          => $marginal,
                'first_commit'   => $firstCommit,
                'comment'        => Arr::get($modelData, 'comment'),
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ],
        );

        StoreDeployment::run(
            $website,
            [
                'scope'          => $marginal,
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        if (in_array($marginal, ['header', 'footer', 'menu', 'sidebar', 'department', 'sub_department', 'family', 'families_overview', 'family_description', 'product', 'products', 'collection'])) {
            $updateData = [
                "live_{$marginal}_snapshot_id"   => $snapshot->id,
                "published_layout->$marginal"    => $snapshot->layout,
                "published_{$marginal}_checksum" => md5(json_encode($snapshot->layout)),
            ];
        } else {
            $updateData = [
                "published_layout->$marginal" => $snapshot->layout
            ];
        }

        $website->update($updateData);
        // todo family_description
        if (in_array($marginal, ['department', 'sub_department', 'family', 'product', 'products', 'families_overview'])) {
            // Update webpage, web_blocks & their snapshots (unpublished/published)
            UpdateWebBlockToWebsiteAndChild::dispatch($website, WebBlockType::find(data_get($layout, "id")), $marginal, data_get($layout, 'data.fieldValue'))->onQueue('low-priority');
        } elseif (in_array($marginal, ['family_description'])) {
            UpdateDescriptionBlockToWebsiteAndChild::dispatch($website, $layout, $marginal)->onQueue('low-priority');
        }

        if ($marginal == 'footer') {
            Cache::forget("irisData:website:$website->id:footer");
        } elseif ($marginal == 'sidebar') {
            Cache::forget("irisData:website:$website->id:sideBar");
        } else {
            BreakWebsiteCache::run($website);
        }


        if ($customAudit && $oldLayout) {
            $titleAudit             = ucfirst(str_replace('_', ' ', $marginal));
            $website->auditEvent    = "{$marginal}_published";
            $website->isCustomEvent = true;

            if (Arr::has($snapshot->layout, 'data')) {
                $layoutFormatted    = Arr::except(data_get($snapshot->layout, 'data.fieldValue'), ['product']);
                $oldLayoutFormatted = Arr::only(data_get($oldLayout, 'data.fieldValue', []), array_keys($layoutFormatted));
            } else {
                $layoutFormatted    = Arr::except(data_get($snapshot->layout, '*.fieldValue'), ['product']);
                $oldLayoutFormatted = Arr::only(data_get($oldLayout, '*.fieldValue', []), array_keys($layoutFormatted));
            }

            $website->auditCustomOld = [
                ...array_filter(Arr::dot($oldLayoutFormatted), mode: ARRAY_FILTER_USE_BOTH)
            ];

            $website->auditCustomNew = [
                '_published_layout' => "$titleAudit Web Block",
                ...array_filter(Arr::dot($layoutFormatted), mode: ARRAY_FILTER_USE_BOTH)
            ];

            Event::dispatch(new AuditCustom($website));
        }

        return $website;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('publisher_id', $request->user()->id);
        $this->set('publisher_type', class_basename($request->user()));
    }

    public function rules(): array
    {
        return [
            'comment'        => ['sometimes', 'required', 'string', 'max:1024'],
            'publisher_id'   => ['sometimes'],
            'publisher_type' => ['sometimes', 'string'],
            'layout'         => ['sometimes', 'array']
        ];
    }


    public function header(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'header', $this->validatedData);
    }

    public function menu(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'menu', $this->validatedData);
    }

    public function sidebar(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'sidebar', $this->validatedData);
    }

    public function footer(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'footer', $this->validatedData);
    }

    public function department(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'department', $this->validatedData);
    }

    public function subDepartment(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'sub_department', $this->validatedData);
    }

    public function family(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'family', $this->validatedData);
    }

    public function familiesOverview(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'families_overview', $this->validatedData);
    }

    public function familyDescription(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'family_description', $this->validatedData);
    }

    public function product(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'product', $this->validatedData);
    }

    public function products(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'products', $this->validatedData);
    }

    public function collection(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'collection', $this->validatedData);
    }

    public function theme(Website $website, ActionRequest $request): void
    {
        $this->initialisationFromShop($website->shop, $request);
        $this->handle($website, 'theme', $this->validatedData);
    }


    public function action(Website $website, $marginal, $modelData): string
    {
        $this->isAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        $this->handle($website, $marginal, $validatedData);

        return "🚀";
    }


}
