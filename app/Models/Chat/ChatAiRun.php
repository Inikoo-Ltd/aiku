<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\ChatAiRunKindEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * One pass of the AI customer service sweep over the inboxes, so what it did can be read run by run.
 *
 * @property int $id
 * @property int $group_id
 * @property ChatAiRunKindEnum $kind
 * @property array $teams
 * @property string|null $model
 * @property string|null $account
 * @property int|null $started_by_user_id
 * @property \Illuminate\Support\Carbon $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property array $counts
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChatAiOutcome> $outcomes
 */
class ChatAiRun extends Model
{
    use HasFactory;

    public const int IDLE_MINUTES = 30;

    protected $guarded = [];

    protected $attributes = [
        'teams'  => '[]',
        'counts' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'kind'        => ChatAiRunKindEnum::class,
            'teams'       => 'array',
            'counts'      => 'array',
            'started_at'  => 'datetime',
            'finished_at'      => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * Every call that carries the run_id counts: a run that has made none for IDLE_MINUTES (its
     * account ran out of usage, or the session died) no longer keeps its chats from other runs.
     */
    public function recordActivity(): void
    {
        if (!$this->finished_at) {
            $this->update(['last_activity_at' => now()]);
        }
    }

    /**
     * Narrows a chat query to the chats no other run is still working: unclaimed, claimed by this
     * run, claimed longer ago than ChatSession::AI_CLAIM_HOURS, or claimed by a run that finished
     * or has been quiet for IDLE_MINUTES.
     */
    public static function whereNotClaimedByAnotherRun(Builder $query, ?int $runId): Builder
    {
        $claimColumn = $query->qualifyColumn('ai_claim_run_id');

        return $query->where(fn ($unclaimed) => $unclaimed->whereNull($claimColumn)
            ->when($runId, fn ($mine) => $mine->orWhere($claimColumn, $runId))
            ->orWhere($query->qualifyColumn('ai_claimed_at'), '<', now()->subHours(ChatSession::AI_CLAIM_HOURS))
            ->orWhereNotExists(fn (QueryBuilder $run) => $run->from('chat_ai_runs')
                ->whereColumn('chat_ai_runs.id', $claimColumn)
                ->whereNull('chat_ai_runs.finished_at')
                ->whereRaw('coalesce(chat_ai_runs.last_activity_at, chat_ai_runs.started_at) >= ?', [now()->subMinutes(self::IDLE_MINUTES)])));
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ChatAiOutcome::class, 'run_id');
    }
}
