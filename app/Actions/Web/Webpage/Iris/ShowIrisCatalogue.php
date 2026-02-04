<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\Iris\Catalogue\IndexIrisDepartments;
use App\Actions\Iris\Catalogue\IndexIrisFamilies;
use App\Actions\Iris\Catalogue\IndexIrisSubDepartments;
use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\IrisDepartmentResource;
use App\Http\Resources\Catalogue\IrisFamilyResource;
use App\Http\Resources\Catalogue\IrisSubDepartmentResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowIrisCatalogue extends IrisAction
{
    public $context;

    public function handle(ActionRequest $request): LengthAwarePaginator
    {
        return IndexIrisCatalogue::make()->action($this->validatedData, $request);
    }

    public function htmlResponse(LengthAwarePaginator $irisCatalogue, ActionRequest $request): Response
    {
        $response = Inertia::render('Catalogue/CatalogueIris', [
            'tabs' => [
                'current' => $this->validatedData['scope'],
                'navigation' => [
                    ['key' => 'departments', 'label' => 'Departments'],
                    ['key' => 'sub_departments', 'label' => 'Sub Departments'],
                    ['key' => 'families', 'label' => 'Families'],
                    ['key' => 'products', 'label' => 'Products'],
                ],
            ],
            'data' => $irisCatalogue,
        ]);

        return match ($this->validatedData['scope']) {
            'collection' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'collection', parent: $this->validatedData['parent'], prefix: 'collection')),
            'department' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'department', parent: $this->validatedData['parent'], prefix: 'department')),
            'sub_department' =>$response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'sub_department', parent: $this->validatedData['parent'], prefix: 'sub_department')),
            'family' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'family', parent: $this->validatedData['parent'], prefix: 'family')),
            'product' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'product', parent: $this->validatedData['parent'], prefix: 'product')),
            default => $response,
        };
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if(!$this->has('scope')){
            $this->set('scope', 'department');
        }
    }

    public function rules(): array
    {
        // Parent must be either [collection, product, department, sub_department, family]
        
        $acceptedParentType = [
            strtolower(class_basename(Collection::class)),
            strtolower(class_basename(Product::class)),
            ...ProductCategoryTypeEnum::values()
        ];

        return [
            'scope'             => ['required', Rule::in($acceptedParentType)],
            'parent'            => ['sometimes', Rule::in($acceptedParentType)],
            'parentKey'         => ['required_with:parent',
                // Collection
                Rule::when(
                    request('parent') === strtolower(class_basename(Collection::class)),
                    Rule::exists('collections', 'id')
                ),
                // Product
                Rule::when(
                    request('parent') === strtolower(class_basename(Product::class)),
                    Rule::exists('products', 'id')
                ),
                // Department / Sub_department / Family
                Rule::when(
                    in_array(request('parent'), ProductCategoryTypeEnum::values()),
                    Rule::exists('product_categories', 'id')
                        ->where('type', request('parent'))
                ),
            ],
        ];
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($request);
    }
}
