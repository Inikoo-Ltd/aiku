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
        if (!empty($this->rating_main)) {
            return;
        }

        $ratings = [
            $this->rating_a,
            $this->rating_b,
            $this->rating_c,
            $this->rating_d,
            $this->rating_e,
        ];

        $validRatings = array_filter($ratings, fn ($r) => $r !== null);

        if (count($validRatings) === 0) {
            return;
        }

        $this->rating_main = array_sum($validRatings) / count($validRatings);
    }
}
