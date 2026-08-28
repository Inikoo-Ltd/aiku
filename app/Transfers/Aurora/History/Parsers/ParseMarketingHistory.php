<?php

namespace App\Transfers\Aurora\History\Parsers;

use App\Transfers\Aurora\History\HistoryValueExtractor;

class ParseMarketingHistory
{
    protected const array AUDITABLE_TYPE_MAP = [
        'Email Campaign' => 'Mailshot',
        'Email Template' => 'EmailTemplate',
        'Email Campaign Type' => 'MailshotType',
        'Deal Campaign' => 'OfferCampaign',
    ];

    protected const array FIELD_MAP = [
        'Email Template Name' => 'name',
        'Email Template Subject' => 'subject',
        'Email Template State' => 'state',
        'Email Campaign Name' => 'name',
        'Email Campaign Type Status' => 'status',
        'Deal Campaign Name' => 'name',
        'Deal Campaign Description' => 'description',
        'Deal Campaign Valid To' => 'valid_to',
    ];

    public static function classify(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;

        if (!array_key_exists($directObject, self::AUDITABLE_TYPE_MAP)) {
            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        $auditableType = self::AUDITABLE_TYPE_MAP[$directObject];
        $action        = $row->Action ?? null;

        if ($action === 'created') {
            $indirectObject = HistoryValueExtractor::normalize($row->{'Indirect Object'} ?? null);

            if ($indirectObject === null) {
                return ['handling' => 'import', 'event' => 'created', 'field' => null, 'auditable_type' => $auditableType];
            }

            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        if ($action === 'edited') {
            $indirectObject = $row->{'Indirect Object'} ?? null;

            if (array_key_exists($indirectObject, self::FIELD_MAP)) {
                return [
                    'handling' => 'import',
                    'event' => 'updated',
                    'field' => self::FIELD_MAP[$indirectObject],
                    'auditable_type' => $auditableType,
                ];
            }

            return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
        }

        return ['handling' => 'skip', 'event' => null, 'field' => null, 'auditable_type' => null];
    }

    public static function extractValues(object $row, string $event, ?string $field): array
    {
        return match ($event) {
            'created' => self::extractCreatedValues($row),
            'updated' => self::extractUpdatedValues($row, $field),
            default => ['old_values' => [], 'new_values' => [], 'data' => []],
        };
    }

    protected static function extractCreatedValues(object $row): array
    {
        $directObject = $row->{'Direct Object'} ?? null;
        $abstract     = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));

        if ($directObject === 'Email Template' && preg_match('/<b>(.*?)<\/b>\s*email template created/i', $abstract, $matches)) {
            return [
                'old_values' => [],
                'new_values' => [],
                'data' => ['template_category' => trim(strip_tags($matches[1]))],
            ];
        }

        $campaignType = self::detectCampaignType($abstract);

        if ($campaignType !== null) {
            return ['old_values' => [], 'new_values' => [], 'data' => ['campaign_type' => $campaignType]];
        }

        return ['old_values' => [], 'new_values' => [], 'data' => []];
    }

    protected static function detectCampaignType(string $abstract): ?string
    {
        if (preg_match('/^Email campaign \d{4}\.\d{2}\.\d{2}/', $abstract)) {
            return 'scheduled';
        }

        if (preg_match('/Newsletter created|Byl vytvořen zpravodaj|Bol vytvorený informačný bulletin|Boletín creado|Bulletin creat/u', $abstract)) {
            return 'newsletter';
        }

        if (preg_match('/Mailshot for orders in basket|mailing para pedidos en la cesta/iu', $abstract)) {
            return 'abandoned_basket';
        }

        if (preg_match('/Invitation mailshot|Invite Full Mailshot/i', $abstract)) {
            return 'invitation';
        }

        return null;
    }

    protected static function extractUpdatedValues(object $row, ?string $field): array
    {
        if ($field === null) {
            return ['old_values' => [], 'new_values' => [], 'data' => []];
        }

        $details = (string) ($row->{'History Details'} ?? '');
        $table   = HistoryValueExtractor::extractTable($details);

        $old = $table['old'];
        $new = $table['new'];

        if ($old === null && $new === null) {
            $abstract = HistoryValueExtractor::cleanArtifacts((string) ($row->{'History Abstract'} ?? ''));
            $new      = self::extractAbstractNewValue($abstract);

            if ($new === null) {
                return ['old_values' => [], 'new_values' => [], 'data' => []];
            }

            return ['old_values' => [], 'new_values' => [$field => $new], 'data' => []];
        }

        return [
            'old_values' => [$field => $old ?? ''],
            'new_values' => [$field => $new ?? ''],
            'data' => [],
        ];
    }

    protected static function extractAbstractNewValue(string $abstract): ?string
    {
        if (preg_match('/(?:&rArr;|⇒)\s*.*?was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/was changed to\s*(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        if (preg_match('/bolo zmenené na(.*)$/u', $abstract, $matches)) {
            return HistoryValueExtractor::normalize(strip_tags($matches[1]));
        }

        return null;
    }
}
