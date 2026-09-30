<?php

/*
 * Author Louis Perez
 * Created on 30-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Actions\DevOps\GitHub\GetGitHubPullRequest;
use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use RuntimeException;

class UpdateTicketPullRequest extends OrgAction
{
    /**
     * Links the pull request that fixes the ticket, or unlinks it when the link is emptied.
     * GitHub is asked before anything is saved, so a typo or a pull request we cannot see never
     * lands on the ticket.
     *
     * @param array{pull_request_url: string|null} $modelData
     */
    public function handle(Ticket $ticket, array $modelData): Ticket
    {
        $url = trim((string) Arr::get($modelData, 'pull_request_url', ''));

        if ($url === '') {
            $ticket->update(['pull_request_url' => null]);

            return $ticket;
        }

        try {
            $pullRequest = GetGitHubPullRequest::run($url, true);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['pull_request_url' => $exception->getMessage()]);
        }

        $ticket->update(['pull_request_url' => $pullRequest['url'] ?: GetGitHubPullRequest::normaliseUrl($url)]);

        return $ticket;
    }

    public function rules(): array
    {
        return [
            'pull_request_url' => ['present', 'nullable', 'string', 'max:255'],
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
