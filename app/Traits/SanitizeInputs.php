<?php

/*
 * author Louis Perez
 * created on 15-01-2026-13h-06m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Traits;

trait SanitizeInputs
{
    /**
     * Fields that should be sanitized.
     * Need to be declared in Classes that uses it
     *
    */

    protected $doSanitize = false;
    protected array $sanitizeFields = [];

    protected function enableSanitize(): void
    {
        $this->doSanitize = true;
    }

    protected function setSanitizeFields(array $fields): void
    {
        $this->sanitizeFields = $fields;
    }

    protected function sanitizeInputs(): void
    {
        if (!$this->doSanitize || $this->sanitizeFields === []) {
            return;
        }

        foreach ($this->sanitizeFields as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $this->set($field, $this->sanitizeValue($this->get($field)));
        }
    }

    protected function sanitizeValue($value)
    {
        if (is_string($value)) {
            return strip_tags($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeValue($item);
            }
        }

        return $value;
    }
}
