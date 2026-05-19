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
        return $this->post("/api/v1/sendSessionMessage/{$whatsappNumber}", [
            'messageText' => $messageText,
        ]);
    }

    public function sendTemplateMessage(string $whatsappNumber, string $templateName, array $parameters = [], string $broadcastName = ''): array
    {
        return $this->post('/api/v1/sendTemplateMessage', [
            'whatsappNumber'  => $whatsappNumber,
            'template_name'   => $templateName,
            'broadcast_name'  => $broadcastName,
            'parameters'      => $parameters,
        ]);
    }

    public function sendBulkTemplateMessage(string $templateName, array $receivers, string $broadcastName = ''): array
    {
        return $this->post('/api/v1/sendTemplateMessages', [
            'template_name'  => $templateName,
            'broadcast_name' => $broadcastName,
            'receivers'      => $receivers,
        ]);
    }

    public function getContacts(int $pageSize = 20, int $pageNumber = 1): array
    {
        return $this->get('/api/v1/getContacts', [
            'pageSize'   => $pageSize,
            'pageNumber' => $pageNumber,
        ]);
    }

    public function getContact(string $whatsappNumber): array
    {
        return $this->get("/api/v1/getContact/{$whatsappNumber}");
    }

    public function addContact(string $whatsappNumber, string $name, array $customParams = []): array
    {
        return $this->post("/api/v1/addContact/{$whatsappNumber}", [
            'name'         => $name,
            'customParams' => $customParams,
        ]);
    }

    public function getMessages(string $whatsappNumber, int $pageSize = 20, int $pageNumber = 1): array
    {
        return $this->get("/api/v1/getMessages/{$whatsappNumber}", [
            'pageSize'   => $pageSize,
            'pageNumber' => $pageNumber,
        ]);
    }

    public function sendMedia(string $whatsappNumber, string $caption, string $mediaUrl, string $mimeType = 'image/jpeg', string $fileName = ''): array
    {
        return $this->post("/api/v1/sendSessionFile/{$whatsappNumber}", [
            'caption'  => $caption,
            'url'      => $mediaUrl,
            'mimeType' => $mimeType,
            'fileName' => $fileName,
        ]);
    }

    public function getTemplates(): array
    {
        return $this->get('/api/v1/getMessageTemplates');
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
