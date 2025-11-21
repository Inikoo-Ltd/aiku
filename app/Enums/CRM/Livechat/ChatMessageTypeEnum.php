<?php

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

enum ChatMessageTypeEnum: string
{
    use EnumHelperTrait;

    case TEXT = 'text';
    case IMAGE = 'image';
    case FILE = 'file';

    public static function labels(): array
    {
        return [
            'text' => __('Text'),
            'image' => __('Image'),
            'file' => __('File'),
        ];
    }
}