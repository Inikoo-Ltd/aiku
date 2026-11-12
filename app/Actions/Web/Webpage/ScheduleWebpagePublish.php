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
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Books the webpage's draft to be published at a set time by RunScheduledWebpagePublishes.
 * The draft as it is at that time is what goes live, and publishing earlier by hand clears the booking.
 */
class ScheduleWebpagePublish extends OrgAction
{
    use WithActionUpdate;
    use WithWebEditAuthorisation;

    private Webpage $webpage;

    public function handle(Webpage $webpage, User $user, array $modelData): Webpage
    {
        $webpage = $this->update($webpage, [
            'publish_scheduled_at'         => Carbon::parse($modelData['publish_scheduled_at'])->utc(),
            'publish_scheduled_by_user_id' => $user->id,
        ]);

        return $webpage->load('publishScheduledBy');
    }

    public function rules(): array
    {
        return [
            'publish_scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->webpage->canBeScheduledForPublishing()) {
            $validator->errors()->add('publish_scheduled_at', __('This page can not be scheduled for publishing.'));
        }
    }

    public function getValidationMessages(): array
    {
        return [
            'publish_scheduled_at.after' => __('Choose a time in the future.'),
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $request->user(), $this->validatedData);
    }

    public function action(Webpage $webpage, User $user, array $modelData): Webpage
    {
        $this->asAction = true;
        $this->webpage  = $webpage;
        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $user, $this->validatedData);
    }

    /**
     * @return array{scheduled_at: string|null, scheduled_by: string|null}
     */
    public function jsonResponse(Webpage $webpage): array
    {
        return WebpagePublishScheduleResource::make($webpage)->resolve();
    }
}
