<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\Helpers\Snapshot\StoreAnnouncementSnapshot;
use App\Actions\OrgAction;
use App\Actions\Web\WebsiteHydrateAnnouncements;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class StoreAnnouncement extends OrgAction
{
    use WithAttributes;

    private Website $parent;
    private string $scope;

    public $commandSignature = 'announcement:create {website}';

    public function handle(Website $parent, array $modelData): Announcement
    {
        $this->parent = $parent;

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'ulid', Str::ulid());

        /** @var Announcement $announcement */
        $announcement = $parent->announcements()->create($modelData);

        $snapshot = StoreAnnouncementSnapshot::run(
            $announcement,
            [
                'layout' => [
                    'container_properties'  => null,
                    'fields'                => null
                ]
            ],
        );

        $announcement->update(
            [
                'unpublished_snapshot_id' => $snapshot->id
            ]
        );

        WebsiteHydrateAnnouncements::dispatch($parent);

        return $announcement;
    }

    public function htmlResponse(Announcement $announcement): Response
    {
        return Redirect::route('grp.org.shops.show.web.announcements.show', [
            'organisation' => $announcement->website->organisation->slug,
            'shop' => $announcement->website->shop->slug,
            'website' => $announcement->website->slug,
            'announcement'     => $announcement->ulid
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255']
        ];
    }

    public function asController(Shop $shop, Website $website, ActionRequest $request): Announcement
    {
        $this->scope    = 'website';
        $this->parent   = $website;

        $this->initialisationFromShop($shop, $request);

        return $this->handle($website, $this->validatedData);
    }

    public function asCommand(Command $command)
    {
        $customer = Website::where('slug', $command->argument('website'))->first();

        $this->handle($customer, [
            'name' => "Vika Announcement's"
        ]);
    }

    public function action(Website $website, array $objectData): Announcement
    {
        $this->parent   = $website;
        $this->asAction = true;
        $this->initialisation($website->organisation, $objectData);

        return $this->handle($website, $this->validatedData);
    }
}
