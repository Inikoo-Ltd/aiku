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

    public const ACTIVE_THRESHOLD_MINUTES = 10;
    public const WINDOW_HOURS             = 24;

    /** @var string[] */
    private const STATUSES = ['browsing', 'idle', 'new_session', 'waiting_chat', 'active_chat', 'closed_chat'];

    public function handle(Organisation $organisation, ?string $date = null): array
    {
        if ($date) {
            return $this->queryByDate($organisation, $date);
        }

        return $this->queryLive($organisation);
    }

    private function queryByDate(Organisation $organisation, string $date): array
    {
        $rows = WebsiteVisitor::query()
            ->where('website_visitors.organisation_id', $organisation->id)
            ->whereDate('website_visitors.last_seen_at', $date)
            ->leftJoin('websites', 'websites.id', '=', 'website_visitors.website_id')
            ->leftJoin('chat_sessions', function ($join) use ($date) {
                $join->on(function ($q) {
                    $q->whereColumn('website_visitors.web_user_id', 'chat_sessions.web_user_id')
                      ->whereNotNull('website_visitors.web_user_id');
                })->orOn(function ($q) {
                    $q->whereColumn('website_visitors.session_id', 'chat_sessions.visitor_session_id');
                });
                $join->whereDate('chat_sessions.created_at', $date);
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
                'website_visitors.page_views',
                'chat_sessions.id as chat_session_id',
                'chat_sessions.status as chat_status',
                DB::raw('COALESCE(msg_counts.msg_count, 0) as messages_count'),
            ])
            ->get();

        return $rows->groupBy(fn ($r) => $r->website_id)
            ->map(function ($websiteRows, $websiteId) {
                $first = $websiteRows->first();

                $countries = $websiteRows
                    ->groupBy(fn ($r) => $r->country_code ?? 'XX')
                    ->map(function ($countryRows, $countryCode) {
                        $breakdown = array_fill_keys(self::STATUSES, 0);

                        foreach ($countryRows as $row) {
                            if ($row->chat_session_id) {
                                $status = match ($row->chat_status) {
                                    'active'             => 'active_chat',
                                    'waiting'            => $row->messages_count > 0 ? 'waiting_chat' : 'new_session',
                                    'closed', 'resolved' => 'closed_chat',
                                    default              => 'browsing',
                                };
                            } else {
                                $status = $row->page_views > 1 ? 'browsing' : 'idle';
                            }

                            $breakdown[$status]++;
                        }

                        return array_merge(
                            ['country_code' => $countryCode, 'total' => $countryRows->count()],
                            $breakdown
                        );
                    })
                    ->sortByDesc('total')
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

    private function queryLive(Organisation $organisation): array
    {
        $activeThreshold = now()->subMinutes(self::ACTIVE_THRESHOLD_MINUTES);
        $windowStart     = now()->subHours(self::WINDOW_HOURS);

        $rows = WebsiteVisitor::query()
            ->where('website_visitors.organisation_id', $organisation->id)
            ->where('website_visitors.last_seen_at', '>=', $windowStart)
            ->leftJoin('websites', 'websites.id', '=', 'website_visitors.website_id')
            ->leftJoin('chat_sessions', function ($join) {
                $join->on(function ($q) {
                    $q->whereColumn('website_visitors.web_user_id', 'chat_sessions.web_user_id')
                      ->whereNotNull('website_visitors.web_user_id');
                })->orOn(function ($q) {
                    $q->whereColumn('website_visitors.session_id', 'chat_sessions.visitor_session_id');
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
            ->map(function ($websiteRows, $websiteId) use ($activeThreshold) {
                $first = $websiteRows->first();

                $countries = $websiteRows
                    ->groupBy(fn ($r) => $r->country_code ?? 'XX')
                    ->map(function ($countryRows, $countryCode) use ($activeThreshold) {
                        $breakdown = array_fill_keys(self::STATUSES, 0);

                        foreach ($countryRows as $row) {
                            $status = $this->resolveStatus($row, $activeThreshold);
                            $breakdown[$status]++;
                        }

                        return array_merge(
                            ['country_code' => $countryCode, 'total' => $countryRows->count()],
                            $breakdown
                        );
                    })
                    ->sortByDesc('total')
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

    private function resolveStatus(object $row, Carbon $activeThreshold): string
    {
        if ($row->chat_session_id) {
            return match ($row->chat_status) {
                'active'             => 'active_chat',
                'waiting'            => $row->messages_count > 0 ? 'waiting_chat' : 'new_session',
                'closed', 'resolved' => 'closed_chat',
                default              => 'browsing',
            };
        }

        return $row->last_seen_at >= $activeThreshold ? 'browsing' : 'idle';
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $date = $request->query('date');

        return $this->handle($organisation, $date ?: null);
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return response()->json($data);
    }
}
