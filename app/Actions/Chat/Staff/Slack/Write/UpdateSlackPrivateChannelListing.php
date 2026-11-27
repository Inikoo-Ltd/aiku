<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Models\Chat\SlackChannel;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Any member of a private channel may show its name to the rest of the staff, so they can ask to join, or hide it again.
 * Off by default: a private channel's name is as private as its messages until someone in it says otherwise.
 */
class UpdateSlackPrivateChannelListing
{
    use AsAction;

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('slackChannel')->isPrivateVisibleTo($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['is_listed' => ['required', 'boolean']];
    }

    public function handle(SlackChannel $slackChannel, bool $isListed): SlackChannel
    {
        $slackChannel->update(['is_listed' => $isListed]);

        return $slackChannel;
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): RedirectResponse
    {
        $this->handle($slackChannel, $request->boolean('is_listed'));

        return back();
    }
}
