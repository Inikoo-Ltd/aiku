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

class RestoreChatAgent
{
    use AsAction;

    public function handle(ChatAgent $chatAgent, Organisation $organisation): void
    {
        $restored = $chatAgent->shopAssignments()
            ->where('organisation_id', $organisation->id)
            ->onlyTrashed()
            ->restore();

        if (! $restored) {
            if (request()->expectsJson()) {
                throw new HttpResponseException(
                    response()->json(['success' => false, 'message' => __('No deleted assignment found for this organisation.')], 422)
                );
            }

            session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('No deleted assignment found for this organisation.'),
            ]);

            throw new HttpResponseException(redirect()->back());
        }
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
            'description' => __('Agent restored successfully.'),
        ]);

        return redirect()->back();
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('Chat agent restored successfully.'),
        ]);
    }
}
