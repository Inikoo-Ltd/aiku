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
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateAnnouncement extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private bool $asAction = false;

    private Customer|Website $parent;
    private string $scope;
    private Customer $customer;

    public function handle(Announcement $announcement, array $modelData): void
    {
        $snapshot = $announcement->unpublishedSnapshot;

        $snapshot->update(
            [
                'layout' => [
                    'container_properties'  => $modelData['container_properties'],
                    'fields'                => $modelData['fields'],
                    'settings'              => $modelData['settings']
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
        if ($this->asAction) {
            return true;
        }

        return $request->get('customerUser')->hasPermissionTo("portfolio.banners.edit");
    }

    public function rules(): array
    {
        return [
            'template_code'        => ['sometimes', 'string'],
            'fields'               => ['sometimes', 'array'],
            'settings'             => ['sometimes', 'array'],
            'container_properties' => ['sometimes', 'array']
        ];
    }

    public function asController(Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->scope    = 'website';
        $this->parent   = $website;
        $this->initialisation($website->organisation, $request);

        $this->handle($announcement, $request->validated());
    }
}
