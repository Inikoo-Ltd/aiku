<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage;

use App\Actions\Comms\Mailbox\SendChatMessageByGmail;
use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SendSupplierEmail extends OrgAction
{
    private ?SupplierMessage $inReplyTo = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * Sent through the procurement Gmail mailbox, so it sits in the mailbox's sent folder exactly as
     * if it had been written there, and read back through the same import as everything else: one
     * way into the inbox, whoever wrote the mail.
     *
     * @param  array{to: array<int, string>, cc?: array<int, string>, subject: string, body: string, attachments?: array<int, UploadedFile>}  $modelData
     */
    public function handle(Organisation $organisation, User $user, array $modelData, ?SupplierMessage $inReplyTo = null, OrgSupplier|OrgAgent|OrgPartner|null $counterpart = null): SupplierMessage
    {
        $client = GmailClient::forProcurement($organisation);

        if (! $client) {
            throw ValidationException::withMessages(['to' => __('Connect the procurement mailbox in Procurement settings before sending emails.')]);
        }

        $mailbox   = (string) Arr::get($organisation->settings, 'procurement.gmail.email');
        $messageId = '<'.Str::ulid().'@'.(Str::after($mailbox, '@') ?: 'aiku.io').'>';

        $result = $client->send(
            $this->rawMessage($organisation, $mailbox, $messageId, $modelData, $inReplyTo),
            $inReplyTo?->gmail_thread_id
        );

        $supplierMessage = SupplierMessage::where('gmail_message_id', $result['id'])->first()
            ?? ProcessProcurementEmail::run($organisation, $result['id']);

        $supplierMessage->update(['user_id' => $user->id]);

        if ($counterpart && ! $supplierMessage->counterpart()) {
            AssignSupplierMessage::make()->handle($supplierMessage, $counterpart);
        }

        return $supplierMessage->refresh();
    }

    private function rawMessage(Organisation $organisation, string $mailbox, string $messageId, array $modelData, ?SupplierMessage $inReplyTo): string
    {
        $subject = trim($modelData['subject']);

        if ($inReplyTo && ! Str::startsWith(Str::lower($subject), 're:')) {
            $subject = 'Re: '.$subject;
        }

        $headers = [
            'From: '.SendChatMessageByGmail::mailbox($mailbox, $organisation->name),
            'To: '.implode(', ', $modelData['to']),
            'Subject: '.SendChatMessageByGmail::encodeHeader($subject),
            "Message-ID: $messageId",
            'MIME-Version: 1.0',
        ];

        if ($cc = Arr::get($modelData, 'cc', [])) {
            $headers[] = 'Cc: '.implode(', ', $cc);
        }

        if ($inReplyTo?->header_message_id) {
            $headers[] = "In-Reply-To: $inReplyTo->header_message_id";
            $headers[] = 'References: '.trim($inReplyTo->header_references.' '.$inReplyTo->header_message_id);
        }

        $bodyPart = [
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($modelData['body'])),
        ];

        $attachments = Arr::get($modelData, 'attachments', []);

        if (! $attachments) {
            return implode("\r\n", [...$headers, ...$bodyPart]);
        }

        $boundary = 'aiku-'.bin2hex(random_bytes(12));
        $lines    = [...$headers, "Content-Type: multipart/mixed; boundary=\"$boundary\"", '', "--$boundary", ...$bodyPart];

        foreach ($attachments as $attachment) {
            $fileName = SendChatMessageByGmail::encodeHeader(str_replace(['"', "\r", "\n"], '', $attachment->getClientOriginalName()));

            array_push(
                $lines,
                "--$boundary",
                'Content-Type: '.($attachment->getMimeType() ?: 'application/octet-stream')."; name=\"$fileName\"",
                "Content-Disposition: attachment; filename=\"$fileName\"",
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode((string) file_get_contents($attachment->getRealPath()))),
            );
        }

        $lines[] = "--$boundary--";

        return implode("\r\n", $lines);
    }

    public function rules(): array
    {
        return [
            'to'            => ['required', 'array', 'min:1'],
            'to.*'          => ['required', 'email'],
            'cc'            => ['sometimes', 'array'],
            'cc.*'          => ['email'],
            'subject'       => ['required', 'string', 'max:900'],
            'body'          => ['required', 'string'],
            'attachments'   => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
            'counterpart'   => ['sometimes', 'nullable', 'string', 'regex:/^(supplier|agent|partner):\d+$/'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->sendAndShow($request);
    }

    public function inReply(Organisation $organisation, SupplierMessage $supplierMessage, ActionRequest $request): RedirectResponse
    {
        abort_unless($supplierMessage->organisation_id === $organisation->id, 404);

        $this->inReplyTo = $supplierMessage;
        $this->initialisation($organisation, $request);

        return $this->sendAndShow($request);
    }

    private function sendAndShow(ActionRequest $request): RedirectResponse
    {
        $counterpart = filled(Arr::get($this->validatedData, 'counterpart'))
            ? AssignSupplierMessage::findCounterpart($this->organisation, $this->validatedData['counterpart'])
            : null;

        $supplierMessage = $this->handle(
            $this->organisation,
            $request->user(),
            Arr::only($this->validatedData, ['to', 'cc', 'subject', 'body', 'attachments']),
            $this->inReplyTo,
            $counterpart
        );

        return redirect()->route('grp.org.procurement.supplier_messages.show', [$this->organisation->slug, $supplierMessage->id])
            ->with('notification', ['status' => 'success', 'title' => __('Email sent')]);
    }
}
