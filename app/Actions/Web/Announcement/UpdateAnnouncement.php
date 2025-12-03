<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateAnnouncement extends OrgAction
{
    use WithActionUpdate;

    private Customer|Website $parent;
    private string $scope;
    private Customer $customer;

    public function handle(Announcement $announcement, array $modelData): void
    {
        $snapshot = $announcement->unpublishedSnapshot;

        $snapshot->update(
            [
                'layout' => [
                    'container_properties' => Arr::get($modelData, 'container_properties', Arr::get($snapshot->layout, 'container_properties')),
                    'fields' => Arr::get($modelData, 'fields', Arr::get($snapshot->layout, 'fields')),
                    'settings' => Arr::get($modelData, 'settings', Arr::get($snapshot->layout, 'settings'))
                ]
            ]
        );
        $announcement->update(
            [
                'is_dirty' => true
            ]
        );

        $this->update($announcement, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string'],
            'template_code'        => ['sometimes', 'string'],
            'fields'               => ['sometimes', 'array'],
            'settings'             => ['sometimes', 'array'],
            'container_properties' => ['sometimes', 'array']
        ];
    }

    public function asController(Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->scope    = 'website';
        $this->parent   = $website;
        $this->initialisation($website->organisation, $request);

        $this->handle($announcement, $this->validatedData);
    }
}
