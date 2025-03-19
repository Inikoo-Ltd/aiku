<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\OrgAction;

class TextChunker extends OrgAction
{
    public static function handle(string $text, int $chunkSize = 600, int $overlapSize = 100): array
    {
        $chunks = [];
        $textLength = strlen($text);

        // Calculate where the first chunk starts and the subsequent chunks.
        for ($start = 0; $start < $textLength; $start += ($chunkSize - $overlapSize)) {
            if ($start + $chunkSize > $textLength) {
                // Get the remaining text if it's shorter than the chunk size.
                $chunks[] = substr($text, $start);
                break;
            }

            // Get the chunk from the text.
            $chunks[] = substr($text, $start, $chunkSize);
        }

        return $chunks;
    }
}
