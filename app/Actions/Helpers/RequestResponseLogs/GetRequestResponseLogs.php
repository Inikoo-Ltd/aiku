<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2025 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\RequestResponseLogs;

use App\Actions\OrgAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetRequestResponseLogs extends OrgAction
{
    public function handle(): array
    {
        $logs = DB::connection('aiku')
            ->table('request_response_logs')
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get()
            ->map(function ($log) {
                return [
                    'id'            => $log->id,
                    'url'           => $log->url,
                    'method'        => $log->method,
                    'x_inertia'     => $log->x_inertia,
                    'headers'       => json_decode($log->headers ?? '{}'),
                    'request_body'  => json_decode($log->request_body ?? 'null'),
                    'response_body' => $log->response_body,
                    'status_code'   => $log->status_code,
                    'content_type'  => $log->content_type,
                    'ip_address'    => $log->ip_address,
                    'duration_ms'   => $log->duration_ms,
                    'created_at'    => $log->created_at,
                ];
            })
            ->toArray();

        return [
            'success' => true,
            'data' => $logs,
            'count' => count($logs),
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->jsonResponse($this->handle());
    }

    public function jsonResponse(array $data): JsonResponse
    {
        return response()->json($data);
    }
}
