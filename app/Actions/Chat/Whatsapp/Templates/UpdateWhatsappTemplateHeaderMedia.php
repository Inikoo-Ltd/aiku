<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\WhatsappMediaTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

/**
 * Sets the file shown above a media-header template.
 */
class UpdateWhatsappTemplateHeaderMedia extends OrgAction
{
    public function rules(): array
    {
        return [
            'header_media' => ['required', $this->mediaRule()],
        ];
    }

    protected function mediaRule(): File
    {
        $type = WhatsappMediaTypeEnum::fromHeaderFormat($this->headerFormat()) ?? WhatsappMediaTypeEnum::IMAGE;

        return File::types($type->extensions())->max($type->maxKilobytes());
    }

    protected function headerFormat(): string
    {
        $template = request()->route('metaMessageTemplate');

        if (!$template instanceof MetaMessageTemplate) {
            return 'IMAGE';
        }

        $header = collect(Arr::get($template->data ?? [], 'components', []))->firstWhere('type', 'HEADER');

        return (string) Arr::get($header ?? [], 'format', 'IMAGE');
    }

    public function handle(MetaMessageTemplate $metaMessageTemplate, UploadedFile $file): MetaMessageTemplate
    {
        $media = StoreMediaFromFile::run(
            $metaMessageTemplate,
            [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'checksum'     => md5_file($file->getPathName()),
            ],
            'template_header',
            str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file'
        );

        $metaMessageTemplate->update(['header_media_id' => $media->id]);

        return $metaMessageTemplate->fresh();
    }

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($metaMessageTemplate, $request->file('header_media'));

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Header file saved'),
            'description' => __('This file is sent above the message from now on.'),
        ]);
    }
}
