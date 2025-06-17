<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Models\Web;

use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property RedirectTypeEnum $type
 * @property string $from_url Full URL including https scheme from url that will be redirected
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $from_path path to redirect from
 * @property int $from_webpage_id
 * @property int $to_webpage_id webpage where it going to be redirected to
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read \App\Models\Web\Webpage|null $webpage
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Redirect newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Redirect newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Redirect query()
 * @mixin \Eloquent
 */
class Redirect extends Model implements Auditable
{
    use InWebsite;
    use HasHistory;

    protected $casts = [
        'type' => RedirectTypeEnum::class
    ];

    protected $guarded = [];

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function generateTags(): array
    {
        return [
            'websites'
        ];
    }

    protected array $auditInclude = [
        'type',
        'url',
    ];


}
