<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026 01:30:00 Coordinated Universal Time
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\UI;

use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateNightOwlIssue
{
    use AsAction;

    public const array STATUSES = ['open', 'resolved', 'ignored'];

    public const array PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasGroupAccess();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'status'   => ['sometimes', 'in:'.implode(',', self::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'in:'.implode(',', self::PRIORITIES)],
        ];
    }

    /** @param array{status?: string, priority?: string|null} $changes */
    public function handle(int $issueId, array $changes, User $user): bool
    {
        $updated = DB::connection('nightowl')->transaction(function ($nightowl) use ($issueId, $changes, $user) {
            $issue = $nightowl->table('nightowl_issues')->where('id', $issueId)->lockForUpdate()->first(['status', 'priority']);
            if (! $issue) {
                return false;
            }

            $now      = now()->utc()->toDateTimeString();
            $activity = collect(['status' => 'status_changed', 'priority' => 'priority_changed'])
                ->filter(fn (string $action, string $field) => array_key_exists($field, $changes) && $changes[$field] !== $issue->$field)
                ->map(fn (string $action, string $field) => [
                    'issue_id'   => $issueId,
                    'user_id'    => (string) $user->id,
                    'user_name'  => $user->contact_name ?: $user->username,
                    'action'     => $action,
                    'old_value'  => $issue->$field,
                    'new_value'  => $changes[$field],
                    'created_at' => $now,
                    'actor_type' => 'user',
                ]);

            if ($activity->isEmpty()) {
                return true;
            }

            $nightowl->table('nightowl_issues')->where('id', $issueId)
                ->update([...array_intersect_key($changes, $activity->all()), 'updated_at' => $now]);
            $nightowl->table('nightowl_issue_activity')->insert($activity->values()->all());

            return true;
        });

        foreach (array_keys(GetNightOwlTelemetry::RANGES) as $range) {
            Cache::forget("devops-telemetry-$range");
        }

        return $updated;
    }

    public function asController(int $issueId, ActionRequest $request): bool
    {
        return $this->handle($issueId, $request->validated(), $request->user());
    }

    public function htmlResponse(bool $updated): RedirectResponse
    {
        abort_unless($updated, 404);

        return back();
    }
}
