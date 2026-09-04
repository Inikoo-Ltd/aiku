<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMetaMessageTemplates
{
    use AsAction;

    public function rules(): array
    {
        return [
            'shop_id'      => ['required', 'exists:shops,id'],
            'session_ulid' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * When a session is given, templates authored with merge tags are resolved against
     * that conversation, so the agent is shown the message as the customer will receive
     * it instead of being asked to type the values by hand.
     */
    public function handle(int $shopId, ?MetaChatSession $metaChatSession = null): Collection
    {
        $agent = Auth::user()?->chatAgent;

        return MetaMessageTemplate::where('shop_id', $shopId)
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->map(function (MetaMessageTemplate $template) use ($metaChatSession, $agent) {
                $body = Arr::get(
                    collect(Arr::get($template->data, 'components', []))->firstWhere('type', 'BODY') ?? [],
                    'text',
                    ''
                );

                preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);

                $tags     = Arr::get($template->data ?? [], 'merge_tags.body', []);
                $resolved = $tags && $metaChatSession
                    ? ResolveWhatsappTemplateTags::run($metaChatSession, $tags, $agent)
                    : null;

                return [
                    'id'              => $template->id,
                    'name'            => $template->name,
                    'language'        => $template->language,
                    'category'        => $template->category,
                    'body'            => $body,
                    'parameter_count' => empty($matches[1]) ? 0 : max(array_map('intval', $matches[1])),
                    'merge_tags'      => $tags,
                    'auto_fill'       => (bool) $tags,
                    'missing_tags'    => $resolved['missing'] ?? [],
                    'resolved_values' => $resolved['values'] ?? null,
                    'preview'         => $resolved ? $this->fill($body, $resolved['values']) : null,
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function fill(string $body, array $values): string
    {
        foreach ($values as $index => $value) {
            if ($value !== null) {
                $body = str_replace('{{'.($index + 1).'}}', $value, $body);
            }
        }

        return $body;
    }

    public function asController(ActionRequest $request): Collection
    {
        $ulid = $request->validated('session_ulid');

        return $this->handle(
            (int) $request->validated('shop_id'),
            $ulid ? MetaChatSession::where('ulid', $ulid)->first() : null
        );
    }

    public function jsonResponse(Collection $templates): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $templates,
        ]);
    }
}
