<?php

/*
 * author Louis Perez
 * created on 30-01-2026-14h-35m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasSearchableText
{
    public function normalizeSearchableText(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $value = Str::ascii($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        return " $value ";
    }

    public function buildSearchableText(array $fields): string
    {
        return implode('', array_map(
            fn ($field) => $this->normalizeSearchableText($field),
            $fields
        ));
    }

    public function syncSearchableText(): void
    {
        if (!property_exists($this, 'searchable_columns')) {
            return;
        }

        $values = collect($this->searchable_columns)
            ->map(fn ($attribute) => $this->{$attribute} ?? null)
            ->all();

        // I add the empty space ' ' at start, do not remove it please
        // Since we search the query like so
        // $query->where('searchable_text', 'ILIKE', "% {$searchToken}%");
        // Otherwise, if searchable_text looking like "ob 195a floralna maslena gorelka zielta", the ob won't be trackable.
        $this->searchable_text = ' '.trim(preg_replace('/\s+/', ' ', $this->buildSearchableText($values)));;
    }
}
