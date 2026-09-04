<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Enums\CRM\Livechat;

use App\Enums\EnumHelperTrait;

/**
 * What WhatsApp actually accepts, per Meta's "Supported Media Types".
 */
enum WhatsappMediaTypeEnum: string
{
    use EnumHelperTrait;

    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case AUDIO = 'audio';

    /**
     * @return array<int, string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::IMAGE => ['image/jpeg', 'image/png'],
            self::VIDEO => ['video/mp4', 'video/3gpp'],
            self::AUDIO => ['audio/aac', 'audio/amr', 'audio/mpeg', 'audio/mp4', 'audio/ogg'],
            self::DOCUMENT => [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::IMAGE    => ['jpg', 'jpeg', 'png'],
            self::VIDEO    => ['mp4', '3gp'],
            self::AUDIO    => ['aac', 'amr', 'mp3', 'm4a', 'ogg'],
            self::DOCUMENT => ['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        };
    }

    /**
     * Meta's ceiling in kilobytes.
     */
    public function maxKilobytes(): int
    {
        return match ($this) {
            self::IMAGE    => 10 * 1024,
            self::VIDEO    => 50 * 1024,
            self::AUDIO    => 16 * 1024,
            self::DOCUMENT => 100 * 1024,
        };
    }

    /**
     * The `accept` attribute for a file input.
     */
    public function accept(): string
    {
        return implode(',', $this->mimeTypes());
    }

    public static function fromHeaderFormat(string $headerFormat): ?self
    {
        return match ($headerFormat) {
            'IMAGE'    => self::IMAGE,
            'VIDEO'    => self::VIDEO,
            'DOCUMENT' => self::DOCUMENT,
            default    => null,
        };
    }

    /**
     * Everything the frontend needs to reject a file before it is uploaded.
     *
     * @return array<string, array{mime_types: array<int, string>, extensions: array<int, string>, max_kb: int, accept: string}>
     */
    public static function forFrontend(): array
    {
        $rules = [];

        foreach (self::cases() as $case) {
            $rules[$case->value] = [
                'mime_types' => $case->mimeTypes(),
                'extensions' => $case->extensions(),
                'max_kb'     => $case->maxKilobytes(),
                'accept'     => $case->accept(),
            ];
        }

        return $rules;
    }
}
