<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Services\Gmail;

use App\Models\Catalogue\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class GmailClient
{
    /**
     * Drive is read because mail is not always where the pictures are: anything over Gmail's
     * attachment limit is sent as a Drive link instead, and the photograph a customer is
     * talking about then lives there. A mailbox connected before this scope existed keeps
     * working and simply has no Drive access until it is reconnected.
     */
    public const string SCOPES = 'https://www.googleapis.com/auth/gmail.modify https://www.googleapis.com/auth/drive.readonly';

    private const string OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string API_BASE_URL = 'https://gmail.googleapis.com/gmail/v1/';

    private const string DRIVE_BASE_URL = 'https://www.googleapis.com/drive/v3/';

    public function __construct(private readonly Shop $shop)
    {
    }

    public static function forShop(Shop $shop): ?self
    {
        if (blank(Arr::get($shop->settings, 'gmail.refresh_token'))) {
            return null;
        }

        return new self($shop);
    }

    public static function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::OAUTH_AUTH_URL.'?'.http_build_query([
            'client_id'              => config('services.gmail.client_id'),
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'scope'                  => self::SCOPES,
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'state'                  => $state,
            'include_granted_scopes' => 'true',
        ]);
    }

    public static function exchangeCode(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'client_id'     => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ])->throw();

        return $response->json();
    }

    public function accessToken(): string
    {
        return Cache::remember("gmail-access-token:{$this->shop->id}", now()->addMinutes(50), function () {
            $refreshToken = Crypt::decryptString((string) Arr::get($this->shop->settings, 'gmail.refresh_token'));

            $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
                'client_id'     => config('services.gmail.client_id'),
                'client_secret' => config('services.gmail.client_secret'),
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token',
            ])->throw();

            return $response->json('access_token');
        });
    }

    public function profile(): array
    {
        return $this->get('users/me/profile')->json();
    }

    /**
     * @return array{message_ids: array<int, string>, history_id: string}
     *
     * @throws GmailHistoryExpiredException
     */
    public function listHistory(string $startHistoryId): array
    {
        $messageIds = [];
        $historyId  = $startHistoryId;
        $pageToken  = null;

        do {
            $query = [
                'startHistoryId' => $startHistoryId,
                'historyTypes'   => 'messageAdded',
                'labelId'        => 'INBOX',
            ];

            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::withToken($this->accessToken())->get(self::API_BASE_URL.'users/me/history', $query);

            if ($response->status() === 404) {
                throw new GmailHistoryExpiredException("Gmail history $startHistoryId has expired for shop {$this->shop->id}");
            }

            $response->throw();

            foreach ($response->json('history', []) as $historyRecord) {
                foreach (Arr::get($historyRecord, 'messagesAdded', []) as $messageAdded) {
                    if (in_array('INBOX', Arr::get($messageAdded, 'message.labelIds', []), true)) {
                        $messageIds[] = Arr::get($messageAdded, 'message.id');
                    }
                }
            }

            $pageToken = $response->json('nextPageToken');

            if ($response->json('historyId')) {
                $historyId = $response->json('historyId');
            }
        } while ($pageToken);

        return [
            'message_ids' => array_values(array_unique($messageIds)),
            'history_id'  => $historyId,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function listInboxMessageIds(string $query = 'in:inbox newer_than:1d', int $maxResults = 50): array
    {
        $response = $this->get('users/me/messages', [
            'q'          => $query,
            'maxResults' => $maxResults,
        ]);

        return array_map(
            static fn (array $message) => $message['id'],
            $response->json('messages', [])
        );
    }

    public function getMessage(string $messageId): array
    {
        return $this->get("users/me/messages/$messageId", ['format' => 'full'])->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getThreadMessages(string $threadId): array
    {
        return $this->get("users/me/threads/$threadId", ['format' => 'full'])->json('messages', []);
    }

    public function getAttachment(string $messageId, string $attachmentId): string
    {
        return GmailMessageParser::decodeData((string) $this->get("users/me/messages/$messageId/attachments/$attachmentId")->json('data'));
    }

    /**
     * What the file is, before deciding whether to take it. Null when the sender never shared it
     * with us, or when the mailbox was connected before we asked for Drive: the link stays in the
     * message either way, which is what the sender sent.
     *
     * @return array{name: string, mimeType: string, size: int}|null
     */
    public function driveFile(string $fileId): ?array
    {
        $response = Http::withToken($this->accessToken())
            ->get(self::DRIVE_BASE_URL."files/$fileId", ['fields' => 'name,mimeType,size']);

        if (! $response->successful()) {
            return null;
        }

        return [
            'name'     => (string) $response->json('name', $fileId),
            'mimeType' => (string) $response->json('mimeType', 'application/octet-stream'),
            'size'     => (int) $response->json('size', 0),
        ];
    }

    public function driveFileContents(string $fileId): string
    {
        return Http::withToken($this->accessToken())
            ->throw()
            ->get(self::DRIVE_BASE_URL."files/$fileId", ['alt' => 'media'])
            ->body();
    }

    public function send(string $rawRfc822, ?string $threadId = null): array
    {
        $payload = ['raw' => rtrim(strtr(base64_encode($rawRfc822), '+/', '-_'), '=')];

        if ($threadId) {
            $payload['threadId'] = $threadId;
        }

        return Http::withToken($this->accessToken())
            ->throw()
            ->post(self::API_BASE_URL.'users/me/messages/send', $payload)
            ->json();
    }

    public function addLabel(string $messageId, string $labelName): void
    {
        $labelId = $this->labelId($labelName);

        Http::withToken($this->accessToken())
            ->throw()
            ->post(self::API_BASE_URL."users/me/messages/$messageId/modify", [
                'addLabelIds' => [$labelId],
            ]);
    }

    public function removeFromInbox(string $messageId): void
    {
        Http::withToken($this->accessToken())
            ->throw()
            ->post(self::API_BASE_URL."users/me/messages/$messageId/modify", [
                'removeLabelIds' => ['INBOX'],
            ]);
    }

    private function labelId(string $labelName): string
    {
        return Cache::remember(
            "gmail-label:{$this->shop->id}:$labelName",
            now()->addDay(),
            function () use ($labelName) {
                $labels = $this->get('users/me/labels')->json('labels', []);

                foreach ($labels as $label) {
                    if ($label['name'] === $labelName) {
                        return $label['id'];
                    }
                }

                return $this->post('users/me/labels', [
                    'name'                  => $labelName,
                    'labelListVisibility'   => 'labelShow',
                    'messageListVisibility' => 'show',
                ])->json('id');
            }
        );
    }

    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken())->throw()->get(self::API_BASE_URL.$path, $query);
    }

    private function post(string $path, array $payload = []): Response
    {
        return Http::withToken($this->accessToken())->throw()->post(self::API_BASE_URL.$path, $payload);
    }
}
