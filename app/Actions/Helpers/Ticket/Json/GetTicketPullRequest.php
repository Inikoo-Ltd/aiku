<?php

/*
 * Author Louis Perez
 * Created on 30-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\DevOps\GitHub\GetGitHubPullRequest;
use App\Actions\OrgAction;
use App\Models\Helpers\Ticket;
use Lorisleiva\Actions\ActionRequest;
use RuntimeException;

class GetTicketPullRequest extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * Asked for by the ticket after it has opened, so a slow GitHub never holds the ticket up.
     * When GitHub will not answer, the reason goes back with a 200 so the page can say it next
     * to the link instead of treating it as a broken request.
     *
     * The commits are only read when asked for, and a failure to read them keeps the pull request.
     *
     * @return array{pull_request: array<string, mixed>|null, commits: array<int, array<string, mixed>>|null, error: string|null}
     */
    public function handle(Ticket $ticket, bool $withCommits = false): array
    {
        if (!$ticket->pull_request_url) {
            return ['pull_request' => null, 'commits' => null, 'error' => null];
        }

        try {
            $pullRequest = GetGitHubPullRequest::run($ticket->pull_request_url);
        } catch (RuntimeException $exception) {
            return ['pull_request' => null, 'commits' => null, 'error' => $exception->getMessage()];
        }

        return ['pull_request' => $pullRequest, 'commits' => $withCommits ? $this->commits($ticket->pull_request_url) : null, 'error' => null];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function commits(string $url): ?array
    {
        try {
            return GetGitHubPullRequest::make()->commits($url);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{pull_request: array<string, mixed>|null, commits: array<int, array<string, mixed>>|null, error: string|null}
     */
    public function asController(Ticket $ticket, ActionRequest $request): array
    {
        abort_unless($ticket->isVisibleTo($request->user()), 403);
        $this->initialisationFromGroup($ticket->group, $request);

        return $this->handle($ticket, $request->boolean('with_commits'));
    }
}
