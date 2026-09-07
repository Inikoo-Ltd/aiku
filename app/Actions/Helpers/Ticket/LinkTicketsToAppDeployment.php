<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Sep 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Models\DevOps\AppDeployment;
use App\Models\Helpers\Ticket;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class LinkTicketsToAppDeployment
{
    use AsAction;

    /**
     * @param  array<int, array{hash: string, subject: string, name?: string, email?: string}>  $commits
     *
     * @return array<string, int> ticket reference => commits linked
     */
    public function handle(AppDeployment $appDeployment, array $commits): array
    {
        $linked = [];

        foreach ($commits as $commit) {
            preg_match_all('/\b(HELP|AD)-(\d+)\b/i', $commit['subject'], $matches);
            foreach (array_unique(array_map('strtoupper', $matches[0])) as $reference) {
                $ticket = Ticket::where('reference', $reference)->first();
                if (!$ticket) {
                    continue;
                }
                $known = collect(data_get($ticket->data, 'commits', []));
                if ($known->contains('hash', $commit['hash'])) {
                    continue;
                }
                $known->push([
                    'hash'          => $commit['hash'],
                    'subject'       => $commit['subject'],
                    'deployment_id' => $appDeployment->id,
                    'version'       => $appDeployment->semantic_version,
                    'deployed_at'   => $appDeployment->created_at?->toIso8601String(),
                ]);
                $ticket->update(['data' => array_merge($ticket->data, ['commits' => $known->values()->all()])]);
                $ticket->comments()->create([
                    'body'        => __('Deployed to production').' ('.($appDeployment->semantic_version ?: Str::limit($appDeployment->commit_hash, 10, '')).'): '.Str::substr($commit['hash'], 0, 10).' '.$commit['subject'],
                    'is_internal' => true,
                ]);
                $linked[$reference] = ($linked[$reference] ?? 0) + 1;
            }
        }

        return $linked;
    }
}
