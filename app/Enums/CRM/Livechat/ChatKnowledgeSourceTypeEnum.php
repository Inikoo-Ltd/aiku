<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatKnowledgeSourceTypeEnum: string
{
    use EnumHelperTrait;

    case TEXT = 'text';
    case FILE = 'file';
    case URL = 'url';

    public static function labels(): array
    {
        return [
            'text' => __('Text note'),
            'file' => __('Uploaded file'),
            'url'  => __('Web page (URL)'),
        ];
    }
}
