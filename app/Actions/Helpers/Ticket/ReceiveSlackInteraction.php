<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Sep 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Ticket;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Actions\Helpers\Ticket\Concerns\WithTicketsWriteGuard;
use App\Models\SysAdmin\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Slack interactivity: the "Raise ticket" shortcut and /ticket open a modal; only its Submit creates the ticket.
 */
class ReceiveSlackInteraction
{
    use AsAction;
    use WithSlack;

    public const string CALLBACK_ID = 'raise_ticket';

    public const string GLOBAL_CALLBACK_ID = 'raise_ticket_global';

    /**
     * @param  array{trigger_id: string, user_id?: string|null, channel_id?: string|null, ts?: string|null, text?: string|null}  $context
     */
    public function openTicketModal(array $context): void
    {
        [$subject, $description] = array_pad(explode("\n", trim((string) Arr::get($context, 'text')), 2), 2, '');

        $response = $this->slackClient()?->post('views.open', [
            'trigger_id' => $context['trigger_id'],
            'view'       => [
                'type'             => 'modal',
                'callback_id'      => self::CALLBACK_ID,
                'private_metadata' => json_encode(Arr::only($context, ['user_id', 'channel_id', 'ts'])),
                'title'            => ['type' => 'plain_text', 'text' => 'Raise a ticket'],
                'submit'           => ['type' => 'plain_text', 'text' => 'Send'],
                'close'            => ['type' => 'plain_text', 'text' => 'Cancel'],
                'blocks'           => [
                    $this->input('subject', 'Subject', array_filter(['type' => 'plain_text_input', 'action_id' => 'value', 'max_length' => 255, 'initial_value' => mb_substr(trim($subject), 0, 255)])),
                    $this->input('description', 'Details: what happens, or what you need and why', array_filter(['type' => 'plain_text_input', 'action_id' => 'value', 'multiline' => true, 'initial_value' => trim($description)]), true),
                    $this->input('reference_url', 'Link to the page: where it breaks, or where the new feature belongs', ['type' => 'url_text_input', 'action_id' => 'value', 'placeholder' => ['type' => 'plain_text', 'text' => 'https://app.aiku.io/...']]),
                    $this->input('files', 'Screenshots (they help a lot)', ['type' => 'file_input', 'action_id' => 'value', 'max_files' => 5], true),
                ],
            ],
        ]);

        if ($response && !$response->json('ok')) {
            Log::warning('Slack views.open failed', ['error' => $response->json('error'), 'response_metadata' => $response->json('response_metadata')]);
        }
    }

    private function input(string $blockId, string $label, array $element, bool $optional = false): array
    {
        return ['type' => 'input', 'block_id' => $blockId, 'optional' => $optional, 'label' => ['type' => 'plain_text', 'text' => $label], 'element' => $element];
    }

    public function asController(Request $request): Response
    {
        abort_unless($this->slackSignatureIsValid($request), 401);
        $payload = json_decode((string) $request->input('payload'), true) ?: [];

        if (WithTicketsWriteGuard::ticketsAreReadOnly()) {
            return response('', 200);
        }

        if (in_array(Arr::get($payload, 'type'), ['message_action', 'shortcut'], true) && in_array(Arr::get($payload, 'callback_id'), [self::CALLBACK_ID, self::GLOBAL_CALLBACK_ID], true)) {
            $this->openTicketModal([
                'trigger_id' => Arr::get($payload, 'trigger_id'),
                'user_id'    => Arr::get($payload, 'user.id'),
                'channel_id' => Arr::get($payload, 'channel.id'),
                'ts'         => Arr::get($payload, 'message.ts'),
                'text'       => Arr::get($payload, 'message.text'),
            ]);
        }

        if (Arr::get($payload, 'type') === 'view_submission' && Arr::get($payload, 'view.callback_id') === self::CALLBACK_ID) {
            $values   = Arr::get($payload, 'view.state.values', []);
            $metadata = json_decode((string) Arr::get($payload, 'view.private_metadata'), true) ?: [];
            $group    = Group::firstOrFail();
            $data     = [
                'user_id'       => Arr::get($payload, 'user.id', Arr::get($metadata, 'user_id')),
                'channel_id'    => Arr::get($metadata, 'channel_id'),
                'ts'            => Arr::get($metadata, 'ts'),
                'subject'       => Arr::get($values, 'subject.value.value'),
                'description'   => Arr::get($values, 'description.value.value'),
                'reference_url' => Arr::get($values, 'reference_url.value.value'),
                'files'         => Arr::get($values, 'files.value.files', []),
            ];
            dispatch(fn () => StoreTicketFromSlack::run($group, $data));
        }

        return response('', 200);
    }
}
