<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\SysAdmin\Organisation;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Carbon\Carbon;

class GetChatDashboardVisitors
{
    use AsAction;

    public const WINDOW_HOURS            = 24;
    public const IDLE_THRESHOLD_MINUTES  = 10;

    public function handle(Organisation $organisation, ?string $date = null): array
    {
        if ($date) {
            $dayStart      = Carbon::parse($date)->startOfDay();
            $windowStart   = $dayStart;
            $windowEnd     = $dayStart->copy()->endOfDay();
            $idleThreshold = $windowEnd->copy()->subMinutes(self::IDLE_THRESHOLD_MINUTES);
        } else {
            $windowStart   = now()->subHours(self::WINDOW_HOURS);
            $windowEnd     = null;
            $idleThreshold = now()->subMinutes(self::IDLE_THRESHOLD_MINUTES);
        }

        $query = WebsiteVisitor::query()
            ->where('website_visitors.organisation_id', $organisation->id)
            ->where('website_visitors.last_seen_at', '>=', $windowStart);

        if ($windowEnd) {
            $query->where('website_visitors.last_seen_at', '<=', $windowEnd);
        }

        $rows = $query
            ->leftJoin('websites', 'websites.id', '=', 'website_visitors.website_id')
            ->leftJoin('chat_sessions', function ($join) {
                $join->on('chat_sessions.website_visitor_id', '=', 'website_visitors.id')
                     ->orOn(function ($q) {
                         $q->whereColumn('website_visitors.web_user_id', 'chat_sessions.web_user_id')
                           ->whereNotNull('website_visitors.web_user_id')
                           ->whereNull('chat_sessions.website_visitor_id');
                     });
            })
            ->leftJoin(
                DB::raw('(SELECT chat_session_id, COUNT(*) as msg_count FROM chat_messages GROUP BY chat_session_id) as msg_counts'),
                'msg_counts.chat_session_id',
                '=',
                'chat_sessions.id'
            )
            ->select([
                'website_visitors.website_id',
                'websites.domain',
                'websites.name as website_name',
                'website_visitors.country_code',
                'website_visitors.last_seen_at',
                'chat_sessions.id as chat_session_id',
                'chat_sessions.status as chat_status',
                DB::raw('COALESCE(msg_counts.msg_count, 0) as messages_count'),
            ])
            ->get();

        return $rows->groupBy(fn ($r) => $r->website_id)
            ->map(function ($websiteRows, $websiteId) use ($idleThreshold) {
                $first = $websiteRows->first();

                $countries = $websiteRows
                    ->groupBy(fn ($r) => $r->country_code ?? 'XX')
                    ->map(function ($countryRows, $countryCode) use ($idleThreshold) {
                        $breakdown = [
                            'active_chat'  => 0,
                            'waiting_chat' => 0,
                            'new_session'  => 0,
                            'closed_chat'  => 0,
                            'browsing'     => 0,
                            'idle'         => 0,
                        ];

                        foreach ($countryRows as $row) {
                            $breakdown[$this->resolveStatus($row, $idleThreshold)]++;
                        }

                        return array_merge(
                            ['country_code' => $countryCode, 'total' => $countryRows->count()],
                            $breakdown
                        );
                    })
                    ->sortBy([
                        fn ($a, $b) => $b['total'] <=> $a['total'],
                        fn ($a, $b) => $a['country_code'] <=> $b['country_code'],
                    ])
                    ->values()
                    ->all();

                return [
                    'website_id'   => (int) $websiteId,
                    'domain'       => $first->domain,
                    'website_name' => $first->website_name,
                    'total'        => $websiteRows->count(),
                    'countries'    => $countries,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function resolveStatus(object $row, Carbon $idleThreshold): string
    {
        if ($row->chat_session_id) {
            return match ($row->chat_status) {
                'active'             => 'active_chat',
                'waiting'            => $row->messages_count > 0 ? 'waiting_chat' : 'new_session',
                'closed', 'resolved' => 'closed_chat',
                default              => 'browsing',
            };
        }

        return $row->last_seen_at >= $idleThreshold ? 'browsing' : 'idle';
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        return $this->handle($organisation, $request->validated('date'));
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return response()->json($data);
    }
}
