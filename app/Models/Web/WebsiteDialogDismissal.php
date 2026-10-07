<?php

namespace App\Models\Web;

use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Web\WebsiteDialogDismissal
 *
 * @property int $id
 * @property int $website_dialog_id
 * @property int $customer_id
 * @property string|null $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read WebsiteDialog $websiteDialog
 * @method static Builder<static>|WebsiteDialogDismissal newModelQuery()
 * @method static Builder<static>|WebsiteDialogDismissal newQuery()
 * @method static Builder<static>|WebsiteDialogDismissal query()
 * @mixin \Eloquent
 */
class WebsiteDialogDismissal extends Model
{
    protected $guarded = [];

    public function websiteDialog(): BelongsTo
    {
        return $this->belongsTo(WebsiteDialog::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
