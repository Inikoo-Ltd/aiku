<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Models\Traits\HasStaticWebsiteSnapshots;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part every page of an info website shares, such as the header with its menu, the footer, or a part
 * added later. Pages hold a marker where it goes, so one change here reaches every page using it.
 *
 * @property int $id
 * @property int $static_website_id
 * @property string $code
 * @property string $html
 * @property \Illuminate\Support\Carbon|null $crawled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read StaticWebsite $staticWebsite
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Snapshot> $snapshots
 */
class StaticWebsiteComponent extends Model
{
    use HasStaticWebsiteSnapshots;

    public const string CODE_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    protected $guarded = [];

    public static function marker(string $code): string
    {
        return '<!--aiku:component:'.$code.'-->';
    }

    /**
     * @return list<string> the codes of the shared parts a page's HTML asks for, in order
     */
    public static function codesIn(string $html): array
    {
        preg_match_all('/<!--aiku:component:('.self::CODE_PATTERN.')-->/', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function labelFor(string $code): string
    {
        return match ($code) {
            'header' => __('Shared header'),
            'footer' => __('Shared footer'),
            default  => __('Shared part: :code', ['code' => $code]),
        };
    }

    public function label(): string
    {
        return self::labelFor($this->code);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'crawled_at' => 'datetime',
        ];
    }

    public function staticWebsite(): BelongsTo
    {
        return $this->belongsTo(StaticWebsite::class);
    }

    public function wasEditedAfterImport(): bool
    {
        return $this->crawled_at && $this->updated_at && $this->updated_at->gt($this->crawled_at);
    }
}
