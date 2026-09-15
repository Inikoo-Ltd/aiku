<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Validation\ValidationException;

/**
 * Turns a refusal from Google into an error on the form field that caused it.
 *
 * Without this a rejected write escapes the action and Laravel answers with a 500, so the person who
 * typed the same headline three times is shown a stack trace instead of the sentence "Assets are
 * duplicated across operations". Google refusing a write is an ordinary outcome, not a server fault:
 * budgets fall under the account minimum, ads fail policy, names collide. All of it belongs on the
 * form.
 */
trait WithGoogleAdsWriteErrors
{
    /**
     * Google answers with a path through its own API. This maps the parts a person can act on back to
     * the field they filled in; anything unrecognised falls back to the field the caller nominates,
     * so an error is always attached somewhere visible rather than swallowed.
     *
     * @throws ValidationException
     */
    protected function refuse(GoogleAdsException $exception, string $fallbackField): never
    {
        throw ValidationException::withMessages([
            $this->formField($exception->fieldPath, $fallbackField) => $exception->getMessage(),
        ]);
    }

    private function formField(?string $fieldPath, string $fallbackField): string
    {
        if ($fieldPath === null) {
            return $fallbackField;
        }

        /* Indexed so the message lands on the third headline rather than on all of them; Inertia
           reads `headlines.2` back onto exactly that input. */
        if (preg_match('/responsive_search_ad\.headlines\[(\d+)]/', $fieldPath, $matches)) {
            return "headlines.{$matches[1]}";
        }

        if (preg_match('/responsive_search_ad\.descriptions\[(\d+)]/', $fieldPath, $matches)) {
            return "descriptions.{$matches[1]}";
        }

        return match (true) {
            str_contains($fieldPath, 'amount_micros')      => 'budget_amount',
            str_contains($fieldPath, 'cpc_bid_ceiling')    => 'max_cpc',
            str_contains($fieldPath, 'cpc_bid_micros')     => 'cpc_bid',
            str_contains($fieldPath, 'final_urls')         => 'final_url',
            str_contains($fieldPath, 'keyword')            => 'keywords',
            str_contains($fieldPath, 'location')           => 'country_codes',
            str_contains($fieldPath, 'ad_group_operation') => 'ad_group_name',
            str_contains($fieldPath, 'campaign_operation') => 'name',
            str_contains($fieldPath, 'status')             => 'status',
            default                                        => $fallbackField,
        };
    }
}
