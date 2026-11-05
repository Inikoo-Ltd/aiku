<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Label;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatLabel;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Renaming or recolouring a label changes it on every conversation that has it, so, as with
 * taking one away, only somebody who supervises the organisation's chat may do it.
 */
class UpdateChatLabel
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        $organisation = $request->route('organisation');
        $label        = $request->route('chatLabel');

        return $organisation instanceof Organisation
            && $label instanceof ChatLabel
            && $label->organisation_id === $organisation->id
            && $this->userSupervisesChatOnOrganisation($request->user(), $organisation);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
    }

    public function rules(ActionRequest $request): array
    {
        $label = $request->route('chatLabel');

        return [
            'name'   => ['required', 'string', 'max:40', Rule::unique('chat_labels', 'name')->where('organisation_id', $label?->organisation_id)->ignore($label?->id)],
            'colour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @param  array{name: string, colour: string}  $modelData
     */
    public function handle(ChatLabel $label, array $modelData): ChatLabel
    {
        $label->update([
            'name'   => trim($modelData['name']),
            'colour' => strtolower($modelData['colour']),
        ]);

        return $label;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, ChatLabel $chatLabel, ActionRequest $request): ChatLabel
    {
        return $this->handle($chatLabel, $request->validated());
    }

    public function jsonResponse(ChatLabel $label): JsonResponse
    {
        return response()->json(['data' => $label->only(['id', 'name', 'colour'])]);
    }
}
