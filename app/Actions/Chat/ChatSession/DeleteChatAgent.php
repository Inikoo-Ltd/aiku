<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:08:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Models\Chat\ChatAgent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteChatAgent
{
    use AsAction;

    public function handle(ChatAgent $chatAgent, Organisation $organisation): void
    {
        if ($chatAgent->current_chat_count > 0) {
            $this->failWith(__('This agent is still handling active chats.'));
        }

        if ($chatAgent->is_online) {
            $this->failWith(__('This agent is still online.'));
        }

        $chatAgent->shopAssignments()
            ->where('organisation_id', $organisation->id)
            ->delete();
    }

    private function failWith(string $message): never
    {
        if (request()->expectsJson()) {
            throw new HttpResponseException(
                response()->json(['success' => false, 'message' => $message], 422)
            );
        }

        session()->flash('notification', [
            'status'      => 'error',
            'title'       => __('Error!'),
            'description' => $message,
        ]);

        throw new HttpResponseException(redirect()->back());
    }

    public function asController(Organisation $organisation, ActionRequest $request, ChatAgent $agent): void
    {
        $this->handle($agent, $organisation);
    }

    public function htmlResponse(): RedirectResponse
    {
        session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Agent deleted successfully.'),
        ]);

        return redirect()->back();
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('Chat agent deleted successfully.'),
        ]);
    }
}
