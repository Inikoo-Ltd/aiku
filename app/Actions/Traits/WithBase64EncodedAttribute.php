<?php

/*
 * Author Yudhistira Arya
 * Created on 27-08-2026-00h-00m
 * Copyright 2026
*/

namespace App\Actions\Traits;

trait WithBase64EncodedAttribute
{
    public function decodeBase64Attribute(string $encodedAttribute, string $attribute): void
    {
        if ($encodedValue = $this->get($encodedAttribute)) {
            $this->set($attribute, json_decode(base64_decode($encodedValue, true), true));
        }
    }
}
