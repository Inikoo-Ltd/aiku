<?php

namespace App\Services\Wati;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WatiClient
{
    private string $accessUrl;
    private string $token;
    private string $apiBase = '/api/ext/v3';

    public function __construct()
    {
        $this->accessUrl = rtrim((string) config('wati.access_url'), '/');
        $this->token     = (string) config('wati.token');
    }

    public static function make(): static
    {
        return new static();
    }

    public function sendSessionMessage(string $whatsappNumber, string $messageText): array
    {
        return $this->post("{$this->apiBase}/sendSessionMessage/{$whatsappNumber}", [
            'messageText' => $messageText,
        ]);
    }

    public function sendTemplateMessage(string $whatsappNumber, string $templateName, array $parameters = [], string $broadcastName = ''): array
    {
        return $this->post("{$this->apiBase}/sendTemplateMessage", [
            'whatsappNumber'  => $whatsappNumber,
            'template_name'   => $templateName,
            'broadcast_name'  => $broadcastName,
            'parameters'      => $parameters,
        ]);
    }

    public function sendBulkTemplateMessage(string $templateName, array $receivers, string $broadcastName = ''): array
    {
        return $this->post("{$this->apiBase}/sendTemplateMessages", [
            'template_name'  => $templateName,
            'broadcast_name' => $broadcastName,
            'receivers'      => $receivers,
        ]);
    }

    public function getContacts(int $pageSize = 20, int $pageNumber = 1): array
    {
        return $this->get("{$this->apiBase}/contacts", [
            'pageSize'   => $pageSize,
            'pageNumber' => $pageNumber,
        ]);
    }

    public function getContact(string $whatsappNumber): array
    {
        return $this->get("{$this->apiBase}/contacts/{$whatsappNumber}");
    }

    public function addContact(string $whatsappNumber, string $name, array $customParams = []): array
    {
        return $this->post("{$this->apiBase}/contacts", [
            'whatsapp_number' => $whatsappNumber,
            'name'            => $name,
            'custom_params'   => $customParams,
        ]);
    }

    public function updateContacts(array $contacts): array
    {
        return $this->put("{$this->apiBase}/contacts", [
            'contacts' => $contacts,
        ]);
    }

    public function getMessages(string $whatsappNumber, int $pageSize = 20, int $pageNumber = 1): array
    {
        return $this->get("{$this->apiBase}/messages/{$whatsappNumber}", [
            'pageSize'   => $pageSize,
            'pageNumber' => $pageNumber,
        ]);
    }

    public function sendMedia(string $whatsappNumber, string $caption, string $mediaUrl, string $mimeType = 'image/jpeg', string $fileName = ''): array
    {
        return $this->post("{$this->apiBase}/sendSessionFile/{$whatsappNumber}", [
            'caption'  => $caption,
            'url'      => $mediaUrl,
            'mimeType' => $mimeType,
            'fileName' => $fileName,
        ]);
    }

    public function getTemplates(int $pageSize = 20, int $pageNumber = 1): array
    {
        return $this->get("{$this->apiBase}/messageTemplates", [
            'pageSize'   => $pageSize,
            'pageNumber' => $pageNumber,
        ]);
    }

    public function getBroadcastsOverview(string $dateFrom, string $dateTo, ?string $channel = null, ?string $searchString = null): array
    {
        return $this->get("{$this->apiBase}/broadcasts/overview", array_filter([
            'date_from'     => $dateFrom,
            'date_to'       => $dateTo,
            'channel'       => $channel,
            'search_string' => $searchString,
        ], fn ($v) => $v !== null));
    }

    public function getBroadcast(string $broadcastId): array
    {
        return $this->get("{$this->apiBase}/broadcasts/{$broadcastId}");
    }

    public function getBroadcastRecipients(string $broadcastId, int $pageNumber = 1, int $pageSize = 20): array
    {
        return $this->get("{$this->apiBase}/broadcasts/{$broadcastId}/recipients", [
            'page_number' => $pageNumber,
            'page_size'   => $pageSize,
        ]);
    }

    public function getChatbots(int $pageNumber = 1, int $pageSize = 20): array
    {
        return $this->get("{$this->apiBase}/chatbots", [
            'page_number' => $pageNumber,
            'page_size'   => $pageSize,
        ]);
    }

    public function getContactCount(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->get("{$this->apiBase}/contacts/count", array_filter([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ], fn ($v) => $v !== null));
    }

    public function sendTextMessage(string $target, string $text): array
    {
        return $this->post("{$this->apiBase}/conversations/messages/text", [
            'target' => $target,
            'text'   => $text,
        ]);
    }

    public function sendFileMessage(string $target, string $filePath, string $fileName, string $mimeType, ?string $caption = null): array
    {
        $request = $this->http()
            ->asMultipart()
            ->attach('file', file_get_contents($filePath), $fileName, ['Content-Type' => $mimeType]);

        if ($caption !== null) {
            $request = $request->attach('caption', $caption);
        }

        $request = $request->attach('target', $target);

        return $this->handleResponse(
            $request->post("{$this->apiBase}/conversations/messages/file"),
            "{$this->apiBase}/conversations/messages/file"
        );
    }

    public function getMediaFile(string $messageId): string
    {
        $response = $this->http()
            ->accept('application/octet-stream')
            ->get("{$this->apiBase}/conversations/messages/file/{$messageId}");

        if ($response->successful()) {
            return $response->body();
        }

        Log::error('WATI API request failed', [
            'endpoint' => "{$this->apiBase}/conversations/messages/file/{$messageId}",
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);

        return '';
    }

    public function assignConversationOperator(string $target, string $assigneeEmail): array
    {
        return $this->put("{$this->apiBase}/conversations/{$target}/operator", [
            'assignee_email' => $assigneeEmail,
        ]);
    }

    public function updateConversationStatus(string $target, string $status): array
    {
        return $this->put("{$this->apiBase}/conversations/{$target}/status", [
            'status' => $status,
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->baseUrl($this->accessUrl)
            ->acceptJson()
            ->asJson();
    }

    private function get(string $endpoint, array $query = []): array
    {
        return $this->handleResponse(
            $this->http()->get($endpoint, $query),
            $endpoint
        );
    }

    private function post(string $endpoint, array $data = []): array
    {
        return $this->handleResponse(
            $this->http()->post($endpoint, $data),
            $endpoint
        );
    }

    private function put(string $endpoint, array $data = []): array
    {
        return $this->handleResponse(
            $this->http()->put($endpoint, $data),
            $endpoint
        );
    }

    private function handleResponse(Response $response, string $endpoint): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        Log::error('WATI API request failed', [
            'endpoint' => $endpoint,
            'status'   => $response->status(),
            'body'     => $response->body(),
        ]);

        return [
            'result'  => false,
            'message' => $response->json('error') ?? 'WATI API request failed',
            'status'  => $response->status(),
        ];
    }
}
