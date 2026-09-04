<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sends a stored draft to Meta for review, keeping the same row so its header file and
 * variable mapping survive the transition.
 */
class SubmitWhatsappTemplateDraft extends StoreWhatsappMessageTemplate
{
    public function rules(): array
    {
        return [];
    }

    public function afterValidator(\Illuminate\Validation\Validator $validator, ActionRequest $request): void
    {
    }

    /**
     * @return array{ok: bool, message?: string, template?: MetaMessageTemplate}
     */
    public function submit(Shop $shop, MetaMessageTemplate $draft): array
    {
        $input = Arr::get($draft->data ?? [], 'draft', []);

        $modelData = [
            'name'          => $draft->name,
            'category'      => Arr::get($input, 'category') ?: $draft->category,
            'language'      => Arr::get($input, 'language') ?: $draft->language,
            'header_format' => Arr::get($input, 'header_format', 'NONE'),
            'header_text'   => Arr::get($input, 'header_text'),
            'body'          => Arr::get($input, 'body'),
            'footer'        => Arr::get($input, 'footer'),
            'buttons'       => Arr::get($input, 'buttons', []),
        ];

        foreach (['category', 'language', 'body'] as $field) {
            if (blank($modelData[$field])) {
                return ['ok' => false, 'message' => __('The draft is missing its :field.', ['field' => $field])];
            }
        }

        if (in_array($modelData['header_format'], ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $media = $draft->headerMedia;

            if (!$media) {
                return ['ok' => false, 'message' => __('Upload the sample file Meta will review.')];
            }

            ['access_token' => $accessToken] = $this->whatsappCredentials($shop);

            $handle = UploadWhatsappTemplateMedia::make()->fromPath(
                $media->getPath(),
                (string) $media->mime_type,
                (string) $accessToken,
                $shop->organisation
            );

            if (!$handle) {
                return ['ok' => false, 'message' => __('The sample file could not be uploaded to Meta.')];
            }

            $modelData['header_handle'] = $handle;
        }

        return $this->handle($shop, $modelData, $draft);
    }

    public function inTemplate(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        if ($metaMessageTemplate->status !== StoreWhatsappTemplateDraft::STATUS) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Already submitted'),
                'description' => __('This template is no longer a draft.'),
            ]);
        }

        $result = $this->submit($shop, $metaMessageTemplate);

        if (!$result['ok']) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Not submitted'),
                'description' => $result['message'],
            ]);
        }

        return Redirect::route('grp.org.shops.show.chat.whatsapp_templates.index', [
            'organisation' => $organisation->slug,
            'shop'         => $shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Sent for review'),
            'description' => __('Meta is reviewing :name now.', ['name' => $metaMessageTemplate->name]),
        ]);
    }
}
