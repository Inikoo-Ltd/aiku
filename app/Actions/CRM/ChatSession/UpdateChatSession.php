<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatPriorityEnum;

class UpdateChatSession
{
    use AsAction;

      public function rules(): array
    {
        return [
            'priority' => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'rating'   => ['sometimes', 'numeric', 'min:1', 'max:5'],
        ];
    }

    public function handle(ChatSession $chatSession, array $data): array
    {
        $updatedFields = [];

        if (array_key_exists('priority', $data)) {
            $chatSession->priority = $data['priority'];
            $updatedFields['priority'] = $data['priority'];
        }

        if (array_key_exists('rating', $data)) {
            $chatSession->rating = $data['rating'];
            $updatedFields['rating'] = $data['rating'];
        }

        if (!empty($updatedFields)) {
            $chatSession->save();
        }

        return $updatedFields;
    }

    public function asController(ChatSession $chatSession, Request $request): JsonResponse
    {
         $this->validateUlid($chatSession->ulid);
        $validated = $request->validate($this->rules());
        $updatedFields = $this->handle($chatSession, $validated);

        if (empty($updatedFields)) {
            return response()->json([
                'success' => false,
                'message' => 'No fields were updated.',
            ], 400);
        }

        $message = collect(array_keys($updatedFields))
            ->map(fn($field) => match($field) {
                'priority' => 'Priority updated successfully',
                'rating' => 'Rating updated successfully',
                default => ucfirst($field) . ' updated successfully'
            })
            ->implode(', ');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'updated_fields' => $updatedFields
            ]
        ], 200);
    }

    protected function validateUlid($ulid): void
    {
        validator(
            ['session_ulid' => $ulid],
            [
                'session_ulid' => [
                    'required',
                    'string',
                    'ulid',
                    'exists:chat_sessions,ulid'
                ]
            ],
            [
                'session_ulid.required' => 'Session ULID is required',
                'session_ulid.ulid' => 'Invalid ULID format',
                'session_ulid.exists' => 'Chat session not found',
            ]
        )->validate();
    }

}
