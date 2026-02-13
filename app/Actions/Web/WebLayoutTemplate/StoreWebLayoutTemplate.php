<?php

/*
 * author Louis Perez
 * created on 10-02-2026-13h-22m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\GrpAction;
use App\Actions\Helpers\Snapshot\StoreWebpageSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Web\Webpage\Traits\WithWebpageHydrators;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Web\WebLayoutTemplate\WebLayoutTemplateType;
use App\Enums\Web\Webpage\WebpageSeoStructureTypeEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Web\WebBlock;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Website;
use App\Models\Web\Webpage;
use App\Rules\AlphaDashSlash;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

// class StoreWebLayoutTemplate extends GrpAction
// {
//     use AsAction;
//     use WithNoStrictRules;
    
//     public function authorize(ActionRequest $request): bool
//     {
//         if ($this->asAction) {
//             return true;
//         }

//         if (property_exists($this, 'fulfilment') && isset($this->fulfilment)) {
//             return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
//         }

//         return $request->user()->authTo("web.{$this->shop->id}.edit");
//     }

//     /**
//      * @throws \Throwable
//      */
//     public function handle(Webpage|WebBlock $parent, array $modelData): WebLayoutTemplate
//     {
//         $initialData = [];
//         if($parent instanceof Webpage){
//             data_set($initialData, 'type', WebLayoutTemplateType::WEBPAGE->value);
//         }else{
//             data_set($initialData, 'type', WebLayoutTemplateType::WEBBLOCK->value);
//         }

//         return new WebLayoutTemplate();
//     }

//     public function rules(): array
//     {
//         $rules = [
//             'label'    => ['required', 'string'],
//         ];

//         return $rules;
//     }

//     /**
//      * @throws \Throwable
//      */
//     public function asController(Webpage|WebBlock $parent, ActionRequest $request): WebLayoutTemplate
//     {
//         $this->initialisation(group(), $request);

//         return $this->handle($parent, $this->validatedData);
//     }

// }
