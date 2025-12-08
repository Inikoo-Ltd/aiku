<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class CaseSensitive implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public $tableName;

    public $message;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->tableName);
        $column = $query->getGrammar()->wrap($attribute);
        if ($query->whereRaw("lower($column) = lower(?)", [$value])->count() >= 1) {
            $fail('The '.$attribute.' has already been taken.');
        }
    }
}
