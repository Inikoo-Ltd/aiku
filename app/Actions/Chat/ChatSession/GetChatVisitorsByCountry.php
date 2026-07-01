<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatSession;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetChatVisitorsByCountry
{
    use AsAction;

    public function handle(Organisation $organisation, int $days = 30): array
    {
        $shopIds = $organisation->shops()->pluck('id');

        $rows = ChatSession::query()
            ->whereIn('shop_id', $shopIds)
            ->whereNotNull('geo_country_code')
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->selectRaw('geo_country_code, COUNT(*) as total')
            ->groupBy('geo_country_code')
            ->orderByDesc('total')
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($row) => [
            'country_code' => $row->geo_country_code,
            'total'        => (int) $row->total,
            'size'         => max(40, (int) round(120 * ($row->total / $max))),
        ])->values()->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $days = (int) $request->query('days', 30);

        return $this->handle($organisation, $days);
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return response()->json($data);
    }
}
