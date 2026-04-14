<?php

namespace App\Http\Resources\Reviews;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewIoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $review = $this->resource;

        $dateCreated = data_get($review, 'date_created', null);

        return [
            'id'                    => data_get($review, 'id', null), // string
            'source'                => data_get($review, 'source', null), // string
            'rating'                => data_get($review, 'rating', null), // integer, we'll make it nullable indicates no rating yet
            'title'                 => data_get($review, 'title', null), // string
            'comments'              => data_get($review, 'comments', null), // string
            'nps'                   => data_get($review, 'nps', 0), // nothing on reviews.io docs, I'll just set to int since that's what we got anw
            'author'                => [ // always array
                'name'      => data_get($review, 'author.name', null), // string
                'location'  => data_get($review, 'author.location', null), // string
                'email'     => data_get($review, 'author.email', null), // string
            ],
            'date_created'          => $dateCreated ? Carbon::parse(data_get($review, 'date_created', null)) : null, // date. parsed using carbon
            'time_ago'              => data_get($review, 'time_ago', null), // string
            'order_id'              => data_get($review, 'order_id', null), // string
            'sku'                   => data_get($review, 'sku', null), // string
            'product_name'          => data_get($review, 'product_name', null), // string
            'photos'                => data_get($review, 'photos', []), // array of strings
            'videos'                => data_get($review, 'videos', []), // array of objects
            'tags'                  => data_get($review, 'tags', []), // array of strings
        ];
    }
}
