<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Web\WebpagePublishScheduleResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class CancelWebpagePublishSchedule extends OrgAction
{
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    public function handle(Webpage $webpage): Webpage
    {
        return $this->update($webpage, [
            'publish_scheduled_at'         => null,
            'publish_scheduled_by_user_id' => null,
        ]);
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage);
    }

    public function action(Webpage $webpage): Webpage
    {
        $this->asAction = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage);
    }

    /**
     * @return array{scheduled_at: string|null, scheduled_by: string|null}
     */
    public function jsonResponse(Webpage $webpage): array
    {
        return WebpagePublishScheduleResource::make($webpage)->resolve();
    }
}
