<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaMessageTemplates
{
    use AsAction;

    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'exists:shops,id'],
        ];
    }

    public function handle(int $shopId): Collection
    {
        return MetaMessageTemplate::where('shop_id', $shopId)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->map(function (MetaMessageTemplate $template) {
                $body = Arr::get(
                    collect(Arr::get($template->data, 'components', []))->firstWhere('type', 'BODY') ?? [],
                    'text',
                    ''
                );

                preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

                return [
                    'id'              => $template->id,
                    'name'            => $template->name,
                    'language'        => $template->language,
                    'category'        => $template->category,
                    'body'            => $body,
                    'parameter_count' => empty($matches[1]) ? 0 : max(array_map('intval', $matches[1])),
                ];
            })
            ->values();
    }

    public function asController(ActionRequest $request): Collection
    {
        return $this->handle((int) $request->validated('shop_id'));
    }

    public function jsonResponse(Collection $templates): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $templates,
        ]);
    }
}
