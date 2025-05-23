<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 16-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\Imap;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapResearch extends OrgAction
{
    use AsAction;

    private $client;
    public function handle()
    {

    }

    public string $commandSignature = 'xxx';

    public function asCommand($command)
    {
        // $shop = Shop::find(1);
        // $this->client = Client::account('default');
        $client = new ClientManager();
        // $client = $client->account('sandbox_gmail');
        // $client->connect();
        $client = $client->make([
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => false, // Just for testing, otherwise true.
            'username' => env('IMAP_USERNAME'),
            'password' => env('IMAP_PASSWORD'),
            'protocol' => 'imap'
        ]);

        $client->connect();

        $this->client = $client;


        dd($this->getUnreadEmailsFromCustomers(['noreply@md.getsentry.com'], 1));
    }

    public function getUnreadEmailsFromCustomers(array $customerEmails, int $limit = 50): Collection
    {
        try {
            $this->client->connect();
            $folder = $this->client->getFolder('INBOX');

            $unreadEmails = collect();

            foreach ($customerEmails as $customerEmail) {
                $customerUnreadEmails = $this->getUnreadEmailsFromSpecificSender($folder, $customerEmail, $limit);
                $unreadEmails = $unreadEmails->merge($customerUnreadEmails);
            }

            $this->client->disconnect();

            return $unreadEmails->sortByDesc('date');

        } catch (\Exception $e) {
            // Log::error('IMAP Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getUnreadEmailsFromSpecificSender($folder, string $senderEmail, int $limit): Collection
    {
        // Search for unread emails from specific sender
        $messages = $folder->messages()
            ->unseen()
            ->from($senderEmail)
            ->limit($limit)
            ->get();

        $emails = collect();

        foreach ($messages as $message) {
            $emails->push([
                'uid' => $message->getUid(),
                'message_id' => $message->getMessageId(),
                'subject' => $message->getSubject(),
                'from' => [
                    'name' => $message->getFrom()[0]->personal ?? '',
                    'email' => $message->getFrom()[0]->mail ?? ''
                ],
                'to' => $this->formatAddresses($message->getTo()),
                'cc' => $this->formatAddresses($message->getCc()),
                'date' => $message->getDate(),
                'body' => [
                    'text' => $message->getTextBody(),
                    'html' => $message->getHTMLBody()
                ],
                'attachments' => $this->getAttachments($message),
                'flags' => $message->getFlags(),
                'size' => $message->getSize(),
                'customer_email' => $senderEmail
            ]);
        }

        return $emails;
    }

    private function formatAddresses($addresses): array
    {
        if (!$addresses) {
            return [];
        }

        $formatted = [];
        foreach ($addresses as $address) {
            $formatted[] = [
                'name' => $address->personal ?? '',
                'email' => $address->mail ?? ''
            ];
        }

        return $formatted;
    }

    private function getAttachments($message): array
    {
        $attachments = [];

        if ($message->hasAttachments()) {
            foreach ($message->getAttachments() as $attachment) {
                $attachments[] = [
                    'name' => $attachment->getName(),
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getContentType(),
                    'disposition' => $attachment->getDisposition()
                ];
            }
        }

        return $attachments;
    }


}
