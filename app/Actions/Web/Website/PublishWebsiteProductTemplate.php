<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\Helpers\Snapshot\StoreWebsiteSnapshot;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Exception;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class PublishWebsiteProductTemplate extends OrgAction
{
    use WithActionUpdate;
    use WithWebAuthorisation;

    public function handle(Website $website, array $modelData): Website
    {
        $settings = $website->settings;

        $firstCommit = false;
        if ($website->state == WebsiteStateEnum::IN_PROCESS) {
            $firstCommit = true;
        }

        foreach ($website->snapshots()->where('scope', SnapshotScopeEnum::PRODUCT_TEMPLATE)->where('state', SnapshotStateEnum::LIVE)->get() as $liveSnapshot) {
            $firstCommit = false;
            UpdateSnapshot::run($liveSnapshot, [
                'state'           => SnapshotStateEnum::HISTORIC,
                'published_until' => now()
            ]);
        }

        $snapshot = StoreWebsiteSnapshot::run(
            $website,
            [
                'state'          => SnapshotStateEnum::LIVE,
                'published_at'   => now(),
                'layout'         => $website->layout,
                'scope'          => SnapshotScopeEnum::PRODUCT_TEMPLATE,
                'first_commit'   => $firstCommit,
                'comment'        => Arr::get($modelData, 'comment'),
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        StoreDeployment::run(
            $website,
            [
                'scope'          => SnapshotScopeEnum::PRODUCT_TEMPLATE,
                'snapshot_id'    => $snapshot->id,
                'publisher_id'   => Arr::get($modelData, 'publisher_id'),
                'publisher_type' => Arr::get($modelData, 'publisher_type'),
            ]
        );

        data_set($settings, 'product_template', $modelData['data']);
        $website->update(['settings' => $settings]);

        return $website;
    }

    public function rules(): array
    {
        return [
            'comment' => ['sometimes', 'required', 'string', 'max:1024'],
            'data'    => ['required']
        ];
    }


    public function jsonResponse(Website $website): string
    {
        return "🚀";
    }

    public function action(Website $website, array $modelData, bool $strict = true): Website
    {
        $this->strict   = $strict;
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($website, $validatedData);
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisation($website->organisation, $request);

        return $this->handle($website, $this->validatedData);
    }

    public string $commandSignature = 'publish:website {website?}';

    public function asCommand($command): int
    {
        if ($command->argument("website")) {
            try {
                $website = Website::where("slug", $command->argument("website"))->firstOrFail();
            } catch (Exception) {
                $command->error("Website not found");

                return 1;
            }
            $this->action($website, [
                'comment' => "test",
                'data'    => 'template'
            ]);
        }

        return 0;
    }

}
