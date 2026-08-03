<?php

namespace App\Models\Reviews\Traits;

use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait IsReviews
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function calculateAverageRating(): void
    {
        $ratings = [
            $this->rating_a,
            $this->rating_b,
            $this->rating_c,
            $this->rating_d,
            $this->rating_e,
        ];

        $validRatings = array_values(array_filter($ratings, fn ($value) => is_numeric($value)));

        if (count($validRatings) > 0) {
            $this->rating_main = round(array_sum($validRatings) / count($validRatings), 2);
            return;
        }

        if (is_numeric($this->rating_main)) {
            $this->rating_main = round((float) $this->rating_main, 2);
        }
    }
}
