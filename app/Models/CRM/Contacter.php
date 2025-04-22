<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Models\CRM;

use App\Actions\Utils\Abbreviate;
use App\Actions\Utils\ReadableRandomStringGenerator;
use App\Enums\CRM\Contacter\ContacterStateEnum;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Tags\HasTags;

class Contacter extends Model
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasTags;
    use InCustomer;

    protected $casts = [
        'data'                 => 'array',
        'state'                => ContacterStateEnum::class,
    ];

    protected $attributes = [
        'data'     => '{}',
        'location' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'crm'
        ];
    }

    // protected array $auditInclude = [
    //     'contact_name',
    //     'company_name',
    //     'email',
    //     'phone',
    //     'contact_website',
    //     'identity_document_type',
    //     'identity_document_number',
    // ];


    protected static function booted(): void
    {
        static::creating(
            function (Contacter $contacter) {
                $contacter->name = $contacter->company_name == '' ? $contacter->contact_name : $contacter->company_name;
            }
        );
        static::updated(function (Contacter $contacter) {
            if ($contacter->wasChanged(['company_name', 'contact_name'])) {
                $contacter->updateQuietly(
                    [
                        'name' => $contacter->company_name == '' ? $contacter->contact_name : $contacter->company_name
                    ]
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                $slug = '';
                if ($this->email) {
                    $tmp = explode('@', $this->email);
                    if (!empty($tmp[0])) {
                        $slug = substr($tmp[0], 0, 8);
                    }
                }


                $name = $this->company_name ? ' '.Abbreviate::run(string: $this->company_name, maximumLength: 4) : '';
                if ($name == '') {
                    $name = $this->contact_name ? ' '.Abbreviate::run(string: $this->contact_name, maximumLength: 4) : '';
                }
                $slug .= $name;


                if ($slug == '') {
                    $slug = ReadableRandomStringGenerator::run();
                }

                return $slug;
            })
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    // public function subscriptionEvents(): MorphMany
    // {
    //     return $this->morphMany(SubscriptionEvent::class, 'model');
    // }


}
