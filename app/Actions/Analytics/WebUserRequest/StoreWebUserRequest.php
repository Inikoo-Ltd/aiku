<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Analytics\WebUserRequest;

use App\Actions\CRM\WebUser\Hydrators\WebUserHydrateWebUserRequests;
use App\Actions\GrpAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateWebUserRequests;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateWebUserRequests;
use App\Actions\Web\Webpage\Hydrators\WebpageHydrateWebUserRequests;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebUserRequests;
use App\Models\Analytics\WebUserRequest;
use App\Models\CRM\WebUser;
use Lorisleiva\Actions\ActionRequest;

class StoreWebUserRequest extends GrpAction
{
    public function handle(WebUser $webUser, array $modelData): WebUserRequest
    {
        data_set($modelData, 'group_id', $webUser->group_id);
        data_set($modelData, 'organisation_id', $webUser->organisation_id);
        data_set($modelData, 'website_id', $webUser->website_id);

        /** @var WebUserRequest $webUserRequest */
        $webUserRequest = $webUser->webuserRequests()->create($modelData);

        GroupHydrateWebUserRequests::dispatch($webUser->group_id)->delay(900);
        OrganisationHydrateWebUserRequests::dispatch($webUser->organisation_id)->delay(600);
        WebsiteHydrateWebUserRequests::dispatch($webUser->website_id)->delay(300);
        if ($webUserRequest->webpage_id) {
            WebpageHydrateWebUserRequests::dispatch($webUserRequest->webpage_id);
        }
        WebUserHydrateWebUserRequests::dispatch($webUserRequest->web_user_id);

        return $webUserRequest;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'date'         => ['required', 'date'],
            'os'           => ['required', 'string'],
            'route_name'   => ['required', 'string'],
            'route_params' => ['required'],
            'device'       => ['required', 'string'],
            'browser'      => ['required', 'string'],
            'ip_address'   => ['required', 'string'],
            'location'     => ['required']
        ];
    }

    public function action(WebUser $webUser, array $modelData, int $hydratorsDelay = 0): WebUserRequest
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($webUser->group, $modelData);

        return $this->handle($webUser, $this->validatedData);
    }
}
