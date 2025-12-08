<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

trait WithAuroraCleaners
{
    public function sanitiseText($string): string
    {
        return str_replace("\0", '', $string);
    }

    public function clearTextWithHtml($string): string
    {
        if ($string === null) {
            return '';
        }

        $string = preg_replace('#<br\s*/?>#i', "\n", $string);

        return strip_tags(html_entity_decode(htmlspecialchars_decode($string)));
    }

    public function cleanTradeUnitReference(string $reference): string
    {
        $reference = str_replace('&', 'and', $reference);
        $reference = str_replace('/', '_', $reference);
        $reference = preg_replace('/\s/', '_', $reference);
        $reference = preg_replace('/\)$/', '', $reference);
        $reference = str_replace('(', '-', $reference);
        $reference = str_replace(')', '-', $reference);
        $reference = str_replace("'", '', $reference);
        $reference = str_replace(',', '', $reference);
        $reference = str_replace('/', '-', $reference);
        $reference = str_replace('*', '_', $reference);
        $reference = str_replace('[', '', $reference);
        $reference = str_replace(']', '', $reference);
        $reference = str_replace('#', '_', $reference);
        $reference = str_replace(':', '_', $reference);
        $reference = str_replace('ň', 'n', $reference);
        $reference = str_replace('%', 'pct', $reference);
        $reference = str_replace('€', 'eur', $reference);
        $reference = str_replace('$', 'usd', $reference);
        $reference = str_replace('£', 'gbp', $reference);

        $reference = preg_replace('/_+/', '_', $reference);
        $reference = preg_replace('/-+/', '-', $reference);

        /** @noinspection PhpDuplicateArrayKeysInspection */
        /** @noinspection DuplicatedCode */
        $normalizeChars = [
            'Š' => 'S',
            'š' => 's',
            'Ð' => 'Dj',
            'Ž' => 'Z',
            'ž' => 'z',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ñ' => 'N',
            'Ń' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'B',
            'ß' => 'Ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'o',
            'ñ' => 'n',
            'ń' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ý' => 'y',
            'þ' => 'b',
            'ÿ' => 'y',
            'ƒ' => 'f',
            'ă' => 'a',
            'î' => 'i',
            'â' => 'a',
            'ș' => 's',
            'ț' => 't',
            'Ă' => 'A',
            'Î' => 'I',
            'Â' => 'A',
            'Ș' => 'S',
            'Ț' => 'T',
            'č' => 'c',
        ];

        $reference = str_replace('_-_', '-', $reference);
        $reference = str_replace('_+_', '-', $reference);
        $reference = strtr($reference, $normalizeChars);

        return str_replace('--', '-', $reference);
    }

    public function cleanWebpageCode($string): string
    {
        $string = str_replace(' ', '_', $string);
        $string = str_replace('/', '_', $string);
        $string = str_replace('&', '_', $string);
        $string = str_replace('(', '_', $string);
        $string = str_replace(')', '_', $string);
        $string = str_replace('!', '_', $string);
        $string = str_replace('?', '_', $string);
        $string = str_replace("'", '', $string);
        $string = str_replace('%', 'pct', $string);

        /** @noinspection PhpDuplicateArrayKeysInspection */
        /** @noinspection DuplicatedCode */
        $normalizeChars = [
            'Š' => 'S',
            'š' => 's',
            'Ð' => 'Dj',
            'Ž' => 'Z',
            'ž' => 'z',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ñ' => 'N',
            'Ń' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ø' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'B',
            'ß' => 'Ss',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'o',
            'ñ' => 'n',
            'ń' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'ý' => 'y',
            'þ' => 'b',
            'ÿ' => 'y',
            'ƒ' => 'f',
            'ă' => 'a',
            'î' => 'i',
            'â' => 'a',
            'ș' => 's',
            'ț' => 't',
            'Ă' => 'A',
            'Î' => 'I',
            'Â' => 'A',
            'Ș' => 'S',
            'Ț' => 'T',
            'č' => 'c',
        ];

        return strtr($string, $normalizeChars);
    }

    protected function cleanOfferCode($code)
    {
        $code = preg_replace('/-/', '', $code);
        $code = preg_replace('/%/', 'off', $code);
        $code = preg_replace('/\s|@|\$|§|}|€|!/', '', $code);
        $code = preg_replace('/3\/2/', '3x2', $code);
        $code = preg_replace('/\//', '-', $code);
        $code = preg_replace('/\+/', 'plus', $code);

        return $code;
    }
}
