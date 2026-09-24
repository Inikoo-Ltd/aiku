<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\OrgAction;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Models\Helpers\Ticket;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTicketDeployComment extends OrgAction
{
    /**
     * Rewrites the comment held back until the deployment lands. Authorship moves to whoever wrote
     * the text that will actually be posted, so the reporter sees the right name on it.
     *
     * @param array{body: string} $modelData
     */
    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        if ($ticket->status !== TicketStatusEnum::PENDING_DEPLOY) {
            abort(403, 'This ticket is not waiting for a deployment');
        }

        $body   = trim(Arr::get($modelData, 'body', ''));
        $author = request()->user();

        $data = $ticket->data ?? [];

        if ($body === '') {
            $ticket->update(['data' => Arr::except($data, 'deploy_comment')]);

            return $ticket;
        }

        $ticket->update([
            'data' => array_merge($data, [
                'deploy_comment' => [
                    'body'    => $body,
                    'user_id' => $author instanceof User
                        ? $author->id
                        : data_get($data, 'deploy_comment.user_id'),
                ]
            ])
        ]);

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'body' => ['present', 'string', 'max:10000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->route('ticket')->canContributeBy($request->user());
    }

    public function action(Ticket $ticket, array $modelData): Ticket
    {
        $this->asAction = true;
        $this->initialisationFromGroup($ticket->group, $modelData);

        return $this->handle($ticket, $this->validatedData);
    }

    public function asController(Ticket $ticket, ActionRequest $request): Ticket
    {
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
