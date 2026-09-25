<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Tasks\StoreStaffTask;
use App\Http\Resources\Tasks\StaffTaskResource;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStaffTaskFromChatSession
{
    use AsAction;

    public function handle(ChatSession|MetaChatSession $session, User $requester, array $modelData): StaffTask
    {
        $organisationSlug = $session->shop->organisation->slug;
        $chatUrl          = $session instanceof MetaChatSession
            ? route('grp.org.chat.inbox', [$organisationSlug]).'?channel=whatsapp&session='.$session->ulid
            : route('grp.org.chat.inbox.conversation', [$organisationSlug, $session->ulid]);

        return StoreStaffTask::make()->action($requester, array_merge(Arr::except($modelData, ['source_message_id']), [
            'description' => trim(Arr::get($modelData, 'description')."\n\n".__('Chat').': '.$chatUrl),
            'model_type'  => class_basename($session),
            'model_id'    => $session->id,
        ]));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(?string $organisation, ChatSession $chatSession, ActionRequest $request): StaffTaskResource
    {
        return new StaffTaskResource($this->handle($chatSession, $request->user(), $request->all()));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMetaChatSession(?string $organisation, MetaChatSession $metaChatSession, ActionRequest $request): StaffTaskResource
    {
        return new StaffTaskResource($this->handle($metaChatSession, $request->user(), $request->all()));
    }
}
