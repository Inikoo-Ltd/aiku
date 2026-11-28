<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use App\InertiaTable\InertiaTable;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Where people write the messages of the Slack channels kept in aiku, public and private: in Slack or from aiku (INI-473).
 * DMs stay out. The channel table lists public channels only, as a private channel's name belongs to its members.
 * Only messages written by people count: system lines (a subtype other than a reply sent to the channel or a file
 * share) and bot or app posts are left out, except posts sent from aiku, which Slack marks with the aiku app's bot_id
 * although a person wrote them. Per channel, never per person.
 */
class GetSlackWritingFromAiku
{
    use AsObject;

    public const int WEEKS = 12;

    public const string TABLE_PREFIX = 'writing';

    private const array PERSON_SUBTYPES = ['thread_broadcast', 'file_share'];

    /**
     * @return array{share: float|null, previous_share: float|null, messages: int, from_aiku: int, previous_messages: int, previous_from_aiku: int, people_connected: int, people_writing_from_aiku: int, weeks: array<int, array{week: string, from_aiku: int, in_slack: int}>}
     */
    public function handle(Group $group, ?Carbon $now = null): array
    {
        $now       = ($now ?? now())->copy();
        $weekAgo   = $now->copy()->subDays(7);
        $firstWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);

        $totals = $this->messages($group, $weekAgo->copy()->subDays(7), $now)
            ->selectRaw('count(*) filter (where slack_messages.sent_at >= ?) as messages', [$weekAgo])
            ->selectRaw('count(*) filter (where slack_messages.sent_at >= ? and slack_messages.sent_from_aiku) as from_aiku', [$weekAgo])
            ->selectRaw('count(*) filter (where slack_messages.sent_at < ?) as previous_messages', [$weekAgo])
            ->selectRaw('count(*) filter (where slack_messages.sent_at < ? and slack_messages.sent_from_aiku) as previous_from_aiku', [$weekAgo])
            ->selectRaw('count(distinct slack_messages.user_id) filter (where slack_messages.sent_at >= ? and slack_messages.sent_from_aiku) as people_writing_from_aiku', [$weekAgo])
            ->first();

        $weekly = $this->messages($group, $firstWeek, $now)
            ->selectRaw("to_char(date_trunc('week', slack_messages.sent_at), 'YYYY-MM-DD') as week")
            ->selectRaw('count(*) filter (where slack_messages.sent_from_aiku) as from_aiku')
            ->selectRaw('count(*) filter (where not slack_messages.sent_from_aiku) as in_slack')
            ->groupByRaw('1')
            ->get()
            ->keyBy('week');

        $weeks = [];
        for ($week = $firstWeek->copy(); $week->lte($now); $week->addWeek()) {
            $row     = $weekly->get($week->toDateString());
            $weeks[] = ['week' => $week->toDateString(), 'from_aiku' => (int) ($row->from_aiku ?? 0), 'in_slack' => (int) ($row->in_slack ?? 0)];
        }

        $connected = DB::table('slack_connections')
            ->join('users', 'users.id', '=', 'slack_connections.user_id')
            ->where('users.group_id', $group->id)
            ->where('users.status', true)
            ->whereNotNull('slack_connections.token')
            ->whereNull('slack_connections.revoked_at')
            ->distinct()
            ->count('slack_connections.user_id');

        return [
            'share'                    => $this->share((int) $totals->from_aiku, (int) $totals->messages),
            'previous_share'           => $this->share((int) $totals->previous_from_aiku, (int) $totals->previous_messages),
            'messages'                 => (int) $totals->messages,
            'from_aiku'                => (int) $totals->from_aiku,
            'previous_messages'        => (int) $totals->previous_messages,
            'previous_from_aiku'       => (int) $totals->previous_from_aiku,
            'people_connected'         => $connected,
            'people_writing_from_aiku' => (int) $totals->people_writing_from_aiku,
            'weeks'                    => $weeks,
        ];
    }

    private function share(int $fromAiku, int $messages): ?float
    {
        return $messages ? round($fromAiku / $messages * 100, 1) : null;
    }

    private function messages(Group $group, Carbon $from, Carbon $to, bool $publicOnly = false): Builder
    {
        $channels = SlackChannel::query()->where('group_id', $group->id)
            ->where(fn ($query) => $publicOnly ? $query->kept() : $query->kept()->orWhere(fn ($private) => $private->keptPrivate()))
            ->select('id');

        return DB::table('slack_messages')
            ->whereIn('slack_messages.slack_channel_id', $channels)
            ->whereNull('slack_messages.deleted_at')
            ->where('slack_messages.sent_at', '>=', $from)
            ->where('slack_messages.sent_at', '<', $to)
            ->where(fn (Builder $query) => $query->whereNull('slack_messages.subtype')->orWhereIn('slack_messages.subtype', self::PERSON_SUBTYPES))
            ->where(fn (Builder $query) => $query->where('slack_messages.sent_from_aiku', true)->orWhereRaw("slack_messages.data->>'bot_id' is null"));
    }

    public function channels(Group $group, ?Carbon $now = null): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters(self::TABLE_PREFIX);

        $now   = ($now ?? now())->copy();
        $stats = $this->messages($group, $now->copy()->subDays(7), $now, publicOnly: true)
            ->select('slack_messages.slack_channel_id')
            ->selectRaw('count(*) as messages, count(*) filter (where slack_messages.sent_from_aiku) as from_aiku')
            ->groupBy('slack_messages.slack_channel_id');

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('slack_channels.name', 'ilike', '%'.$value.'%');
        });

        return QueryBuilder::for(
            SlackChannel::query()
                ->joinSub($stats, 'stats', 'stats.slack_channel_id', '=', 'slack_channels.id')
                ->select([
                    'slack_channels.id', 'slack_channels.name', 'stats.messages', 'stats.from_aiku',
                    DB::raw('round(stats.from_aiku * 100.0 / stats.messages, 1) as share'),
                ])
        )
            ->defaultSort('-messages')
            ->allowedSorts(['name', 'messages', 'from_aiku', 'share'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(self::TABLE_PREFIX, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table->name(self::TABLE_PREFIX)->pageName(self::TABLE_PREFIX.'Page')
                ->withGlobalSearch()
                ->column(key: 'name', label: __('Channel'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'messages', label: __('Messages, last 7 days'), sortable: true, type: 'number')
                ->column(key: 'from_aiku', label: __('Written from aiku'), sortable: true, type: 'number')
                ->column(key: 'share', label: __('Share'), sortable: true, type: 'number')
                ->defaultSort('-messages');
        };
    }
}
