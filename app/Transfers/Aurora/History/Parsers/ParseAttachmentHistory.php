<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseAttachmentHistory
{
    public static function classify(object $row): array
    {
        $action         = $row->Action ?? null;
        $indirectObject = $row->{'Indirect Object'} ?? null;

        if ($action === 'associated' && $indirectObject === 'Part') {
            return ['handling' => 'import', 'event' => 'attached', 'field' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        if ($event !== 'attached') {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if (!preg_match('/^(?:(\S+)\s+)?Attachment uploaded;?\s*(?:<a[^>]*>([^<]*)<\/a>|<span[^>]*>([^<]*)<\/span>)\s*(?:\(([\d.]+\s*[KMG]?B)\))?\s*(.*)$/is', $abstract, $matches)) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $label    = $matches[1] ?? '';
        $filename = ($matches[2] ?? '') !== '' ? $matches[2] : ($matches[3] ?? '');
        $size     = $matches[4] ?? '';
        $caption  = trim($matches[5] ?? '');

        $data = [];

        if ($size !== '') {
            $data['size'] = trim($size);
        }

        if ($caption !== '') {
            $data['caption'] = $caption;
        }

        if ($label !== '') {
            $data['label'] = $label;
        }

        return ['old_values' => [], 'new_values' => ['filename' => trim($filename)], 'data' => $data];
    }
}
