<?php

/*
 * Author Louis Perez
 * Created on 07-10-2026-15h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket\Json;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;

class GetSimilarTicketsForDraft extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * Tickets related to one still being written, so its reporter can see whether it was already raised.
     *
     * @param  array{subject?: string|null, description?: string|null, refresh?: bool}  $modelData
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $viewer, array $modelData): array
    {
        $subject     = trim((string) ($modelData['subject'] ?? ''));
        $description = trim((string) ($modelData['description'] ?? ''));

        if (count(GetSimilarTickets::words($subject.' '.$description)) < 2) {
            return [];
        }

        $finder   = GetSimilarTickets::make();
        $cacheKey = 'ticket-similar-draft:'.$viewer->group_id.':'.md5($subject."\n".$description);
        $ranking  = ($modelData['refresh'] ?? false) ? null : Cache::get($cacheKey);

        if ($ranking === null) {
            [$ranking, $isJudgedByJev] = $finder->rankText($viewer->group_id, $subject, $description);
            if ($isJudgedByJev) {
                Cache::put($cacheKey, $ranking, now()->addHour());
            }
        }

        return $finder->present($ranking, $viewer);
    }

    public function rules(): array
    {
        return [
            'subject'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'refresh'     => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup($request->user()->group, $request);

        return $this->handle($request->user(), $this->validatedData);
    }
}
