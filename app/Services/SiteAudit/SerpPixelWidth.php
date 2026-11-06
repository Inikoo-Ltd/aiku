<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SiteAudit;

/**
 * The width a text takes in Google's results, from the Arial glyph widths (units per 1000 of the font
 * size). Google cuts titles and descriptions by width, not by characters, so a title of capitals is cut
 * much earlier than one of lower case letters.
 */
class SerpPixelWidth
{
    public const int TITLE_FONT_SIZE = 20;

    public const int DESCRIPTION_FONT_SIZE = 14;

    public const int TITLE_MAX_PIXELS = 561;

    public const int DESCRIPTION_MAX_PIXELS = 985;

    private const int DEFAULT_GLYPH_WIDTH = 556;

    private const array GLYPH_WIDTHS = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191, '(' => 333, ')' => 333, '*' => 389,
        '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278, '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556,
        '6' => 556, '7' => 556, '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667,
        'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667,
        'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, 'a' => 556,
        'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222,
        'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
        'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
    ];

    public static function of(?string $text, int $fontSize): ?int
    {
        if ($text === null || $text === '') {
            return null;
        }

        $units = 0;

        foreach (mb_str_split($text) as $character) {
            $units += self::GLYPH_WIDTHS[$character] ?? self::GLYPH_WIDTHS[self::withoutAccent($character)] ?? self::DEFAULT_GLYPH_WIDTH;
        }

        return (int) round($units * $fontSize / 1000);
    }

    private static function withoutAccent(string $character): string
    {
        $decomposed = class_exists(\Normalizer::class) ? \Normalizer::normalize($character, \Normalizer::FORM_D) : $character;

        return mb_substr((string) $decomposed, 0, 1);
    }
}
