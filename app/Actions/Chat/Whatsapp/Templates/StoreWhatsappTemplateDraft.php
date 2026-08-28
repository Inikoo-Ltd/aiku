<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Keeps a half-written template in Aiku without touching Meta. Meta's rules only bite at
 * submission, so a draft is validated on shape rather than completeness — the point is to
 * be able to walk away from an unfinished template and come back to it.
 */
class StoreWhatsappTemplateDraft extends StoreWhatsappMessageTemplate
{
    public const STATUS = 'DRAFT';

    public function rules(): array
    {
        return [
            'name'                   => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category'               => ['sometimes', Rule::in(self::CATEGORIES)],
            'language'               => ['sometimes', 'string', 'max:10'],

            'header_format'          => ['sometimes', Rule::in(self::HEADER_FORMATS)],
            'header_text'            => ['nullable', 'string', 'max:60'],
            'header_example'         => ['nullable', 'string', 'max:60'],
            'header_media'           => ['nullable', $this->headerMediaRule()],

            'body'                   => ['nullable', 'string', 'max:1024'],
            'body_examples'          => ['sometimes', 'array', 'max:10'],
            'body_examples.*'        => ['nullable', 'string', 'max:200'],

            'footer'                 => ['nullable', 'string', 'max:60'],

            'buttons'                => ['sometimes', 'array', 'max:10'],
            'buttons.*.type'         => ['required', Rule::in(self::BUTTON_TYPES)],
            'buttons.*.text'         => ['nullable', 'string', 'max:25'],
            'buttons.*.url'          => ['nullable', 'string', 'max:2000'],
            'buttons.*.url_example'  => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * The parent's checks exist to keep Meta from rejecting a submission. A draft is never
     * submitted, so they would only stop someone saving work in progress.
     */
    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
    }

    public function handle(Shop $shop, array $modelData, ?MetaMessageTemplate $draft = null): array
    {
        $components = $this->buildComponents($modelData, null, $shop->organisation);

        if (isset($components['error'])) {
            return ['ok' => false, 'message' => $components['error']];
        }

        $metaChannel = MetaChannel::where('code', 'whatsapp')->firstOrFail();

        $attributes = [
            'group_id'        => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'shop_id'         => $shop->id,
            'meta_channel_id' => $metaChannel->id,
            'name'            => $modelData['name'],
            'language'        => Arr::get($modelData, 'language'),
            'category'        => Arr::get($modelData, 'category'),
            'status'          => self::STATUS,
            'data'            => [
                'merge_tags' => ['body' => Arr::get($components, 'tags.body', [])],
                'name'       => $modelData['name'],
                'language'   => Arr::get($modelData, 'language'),
                'category'   => Arr::get($modelData, 'category'),
                'status'     => self::STATUS,
                'components' => $components['components'],
                // The builder's own input, so reopening the draft shows exactly what was
                // typed instead of the compiled {{n}} form Meta would receive.
                'draft'      => $this->draftInput($modelData),
            ],
        ];

        $template = $draft
            ? tap($draft)->update($attributes)
            : MetaMessageTemplate::create(array_merge($attributes, ['template_id' => null]));

        if (($modelData['header_media'] ?? null) instanceof UploadedFile) {
            $this->storeHeaderMedia($template, $modelData['header_media']);
        }

        return ['ok' => true, 'template' => $template->fresh()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function draftInput(array $modelData): array
    {
        return [
            'category'      => Arr::get($modelData, 'category'),
            'language'      => Arr::get($modelData, 'language'),
            'header_format' => Arr::get($modelData, 'header_format', 'NONE'),
            'header_text'   => Arr::get($modelData, 'header_text'),
            'body'          => Arr::get($modelData, 'body'),
            'footer'        => Arr::get($modelData, 'footer'),
            'buttons'       => array_values(Arr::get($modelData, 'buttons', [])),
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->respond($this->handle($shop, $this->validatedData));
    }

    public function inTemplate(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        if ($metaMessageTemplate->status !== self::STATUS) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Not a draft'),
                'description' => __('This template already lives on Meta and cannot be edited here.'),
            ]);
        }

        return $this->respond($this->handle($shop, $this->validatedData, $metaMessageTemplate));
    }

    /**
     * @param  array{ok: bool, message?: string, template?: MetaMessageTemplate}  $result
     */
    protected function respond(array $result): RedirectResponse
    {
        if (!$result['ok']) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Draft not saved'),
                'description' => $result['message'],
            ]);
        }

        return Redirect::route('grp.org.shops.show.chat.whatsapp_templates.draft.edit', [
            'organisation'        => $this->organisation->slug,
            'shop'                => $this->shop->slug,
            'metaMessageTemplate' => $result['template']->id,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Draft saved'),
            'description' => __('Nothing was sent to Meta — submit it for review when it is ready.'),
        ]);
    }
}
