<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2026 14:36:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\AppDeployment;

use App\Actions\Helpers\AI\AskToAi;
use App\Models\DevOps\AppDeployment;
use App\Models\DevOps\Committer;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Process\Process;

class GenerateAppDeploymentChangeLog
{
    use AsAction;

    public function handle(AppDeployment $appDeployment): void
    {
        if (!$appDeployment->commit_hash) {
            return;
        }

        $previousDeployment = AppDeployment::where('id', '<', $appDeployment->id)
            ->whereNotNull('commit_hash')
            ->where('commit_hash', '!=', $appDeployment->commit_hash)
            ->orderByDesc('id')
            ->first();

        if (!$previousDeployment) {
            return;
        }

        $commits = $this->getCommits($previousDeployment->commit_hash, $appDeployment->commit_hash);

        if ($commits === []) {
            return;
        }

        $appDeployment->update([
            'committers' => $this->saveCommitters($commits),
            'change_log' => AskToAi::run($this->getPrompt($commits)),
        ]);
    }

    /**
     * @return array<int, array{hash: string, name: string, email: string, subject: string}>
     */
    private function getCommits(string $from, string $to): array
    {
        $process = new Process(['git', 'log', '--no-merges', '--format=%H|%an|%ae|%s', "$from..$to"], base_path());
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $commits = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            if (substr_count($line, '|') < 3) {
                continue;
            }
            [$hash, $name, $email, $subject] = explode('|', $line, 4);
            $commits[]                       = [
                'hash'    => $hash,
                'name'    => $name,
                'email'   => $email,
                'subject' => $subject,
            ];
        }

        return $commits;
    }

    /**
     * @param array<int, array{hash: string, name: string, email: string, subject: string}> $commits
     * @return array<int, array{name: string, email: string, github_username: string|null, avatar: string|null}>
     */
    private function saveCommitters(array $commits): array
    {
        $committers = [];
        foreach (collect($commits)->unique('email') as $commit) {
            $committer = Committer::firstOrCreate(
                ['email' => $commit['email']],
                ['name' => $commit['name']]
            );

            if (!$committer->avatar) {
                $githubAuthor = $this->getGithubAuthor($commit['hash']);
                if ($githubAuthor) {
                    $committer->update([
                        'github_username' => $githubAuthor['login'],
                        'avatar'          => $githubAuthor['avatar'],
                    ]);
                }
            }

            $committers[] = [
                'name'            => $committer->name,
                'email'           => $committer->email,
                'github_username' => $committer->github_username,
                'avatar'          => $committer->avatar,
            ];
        }

        return $committers;
    }

    /**
     * @return array{login: string|null, avatar: string|null}|null
     */
    private function getGithubAuthor(string $commitHash): ?array
    {
        $repo    = config('services.github.repo');
        $request = Http::connectTimeout(5)->timeout(10);

        if ($token = config('services.github.token')) {
            $request = $request->withToken($token);
        }

        $response = $request->get("https://api.github.com/repos/$repo/commits/$commitHash");

        if (!$response->successful() || !$response->json('author')) {
            return null;
        }

        return [
            'login'  => $response->json('author.login'),
            'avatar' => $response->json('author.avatar_url'),
        ];
    }

    /**
     * @param array<int, array{hash: string, name: string, email: string, subject: string}> $commits
     */
    private function getPrompt(array $commits): string
    {
        $subjects = collect($commits)->pluck('subject')->implode("\n- ");

        return "We just updated our company web app. Below is the list of technical change descriptions from the programmers.\n"
            ."Write a short, friendly summary for the staff who use the app every day. They are NOT technical at all, "
            ."so explain it like you would to a five year old: no jargon, no file names, no code words. "
            ."Only mention things they might actually notice or that affect how they work; skip internal plumbing "
            ."(group those into one line like 'we also did some behind-the-scenes tidying to keep things fast and safe'). "
            ."Use a few short bullet points.\n\nChanges:\n- $subjects";
    }
}
