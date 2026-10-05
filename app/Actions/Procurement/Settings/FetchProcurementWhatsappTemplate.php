<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\Settings;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchProcurementWhatsappTemplate
{
    use AsAction;
    use WithWhatsappCredentials;

    /**
     * Meta filters templates by a partial name, so the exact name is matched here, preferring the
     * procurement template language when the same template exists in several languages.
     *
     * @return array{fetch_status: string, error: string|null, fetched_at: string, template: array<string, mixed>|null}
     */
    public function handle(Organisation $organisation, string $name): array
    {
        ['waba_id' => $wabaId, 'access_token' => $accessToken] = $this->procurementWhatsappCredentials($organisation);

        if ($wabaId === '' || $accessToken === '') {
            return $this->result('not_connected', __('Set the WhatsApp Business Account ID and the organisation Meta access key first.'));
        }

        $response = Http::withToken($accessToken)->get($this->whatsappEndpoint($wabaId.'/message_templates'), [
            'name'   => $name,
            'fields' => 'id,name,language,status,category,components',
            'limit'  => 100,
        ]);

        if ($response->failed()) {
            return $this->result('failed', (string) $response->json('error.message', $response->body()));
        }

        $matches  = collect($response->json('data') ?? [])->where('name', $name);
        $template = $matches->firstWhere('language', Arr::get($organisation->settings, 'procurement.whatsapp.template_language')) ?? $matches->first();

        if (! $template) {
            return $this->result('not_found', __('Meta has no template called :name in this WhatsApp Business Account.', ['name' => $name]));
        }

        return $this->result('found', null, Arr::only($template, ['id', 'name', 'language', 'status', 'category', 'components']));
    }

    /**
     * @param  array<string, mixed>|null  $template
     * @return array{fetch_status: string, error: string|null, fetched_at: string, template: array<string, mixed>|null}
     */
    private function result(string $fetchStatus, ?string $error, ?array $template = null): array
    {
        return [
            'fetch_status' => $fetchStatus,
            'error'        => $error,
            'fetched_at'   => now()->toIso8601String(),
            'template'     => $template,
        ];
    }
}
