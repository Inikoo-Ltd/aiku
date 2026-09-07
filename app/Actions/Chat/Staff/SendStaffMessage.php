<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Events\StaffMessageSent;
use App\Http\Resources\Chat\StaffMessageResource;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendStaffMessage
{
    use AsAction;

    /**
     * @param array{body?: string, parent_id?: int, image?: UploadedFile} $modelData
     */
    public function handle(StaffConversation $conversation, User $sender, array $modelData): StaffMessage
    {
        $body = trim(strip_tags($modelData['body'] ?? ''));

        $message = $conversation->messages()->create([
            'user_id'     => $sender->id,
            'parent_id'   => $modelData['parent_id'] ?? null,
            'body'        => $body,
            'language_id' => $sender->language_id,
            'mentions'    => $this->resolveMentions($conversation, $body),
        ]);

        if (($modelData['image'] ?? null) instanceof UploadedFile) {
            $media = StoreMediaFromFile::run($message, [
                'path'         => $modelData['image']->getPathName(),
                'originalName' => $modelData['image']->getClientOriginalName(),
                'extension'    => $modelData['image']->getClientOriginalExtension(),
                'checksum'     => md5_file($modelData['image']->getPathName()),
            ], 'staff_chat_images');
            $message->updateQuietly(['media_id' => $media->id]);
        }

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->newPivotStatement()->where('staff_conversation_id', $conversation->id)->update(['archived_at' => null]);
        $conversation->participants()->updateExistingPivot($sender->id, ['last_read_at' => $message->created_at]);

        StaffMessageSent::dispatch($message);
        TranslateStaffMessage::dispatch($message->id);

        return $message;
    }

    /**
     * @return array<int, int>
     */
    private function resolveMentions(StaffConversation $conversation, string $body): array
    {
        if (!preg_match_all('/@([\pL\pN._-]+)/u', $body, $matches)) {
            return [];
        }

        $participants = $conversation->participants;
        $mentions     = [];

        foreach ($matches[1] as $token) {
            $participant = $participants->first(
                fn (User $user) => strcasecmp($user->nickname ?? '', $token) === 0 || strcasecmp($user->username ?? '', $token) === 0
            );

            if ($participant) {
                $mentions[$participant->id] = $participant->id;
            }
        }

        return array_values($mentions);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffConversation')->hasParticipant($request->user());
    }

    public function rules(): array
    {
        return [
            'body'      => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image'     => ['required_without:body', 'nullable', 'image', 'max:10240'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:staff_messages,id'],
        ];
    }

    public function asController(StaffConversation $staffConversation, ActionRequest $request): StaffMessageResource
    {
        $message = $this->handle($staffConversation, $request->user(), $request->validated() + ['image' => $request->file('image')]);
        $message->load(['user', 'translations', 'reactions', 'conversation']);

        return new StaffMessageResource($message);
    }
}
