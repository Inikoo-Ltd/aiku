<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
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

class StoreChatLabel
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanViewChatOnOrganisation($request->user(), $request->route('organisation'));
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
    }

    public function rules(ActionRequest $request): array
    {
        return [
            'name'   => ['required', 'string', 'max:40', Rule::unique('chat_labels', 'name')->where('organisation_id', $request->route('organisation')?->id)],
            'colour' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @param  array{name: string, colour: string}  $modelData
     */
    public function handle(Organisation $organisation, array $modelData, ?int $createdBy = null): ChatLabel
    {
        return ChatLabel::create([
            'group_id'        => $organisation->group_id,
            'organisation_id' => $organisation->id,
            'name'            => trim($modelData['name']),
            'colour'          => strtolower($modelData['colour']),
            'created_by'      => $createdBy,
        ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): ChatLabel
    {
        return $this->handle($organisation, $request->validated(), $request->user()->id);
    }

    public function jsonResponse(ChatLabel $label): JsonResponse
    {
        return response()->json(['data' => $label->only(['id', 'name', 'colour'])], 201);
    }
}
