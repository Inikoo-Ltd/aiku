<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisCatalogue;
use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

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
                'navigation' => $this->getTabNavigation($request->query('parent_type', null)),
            ],
            'data' => $irisCatalogue,
        ]);

        return match ($this->validatedData['scope']) {
            'collection' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'collection', parent: data_get($this->validatedData, 'parent', null), prefix: 'collection')),
            'department' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'department', parent: data_get($this->validatedData, 'parent', null), prefix: 'department')),
            'sub_department' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'sub_department', parent: data_get($this->validatedData, 'parent', null), prefix: 'sub_department')),
            'family' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'family', parent: data_get($this->validatedData, 'parent', null), prefix: 'family')),
            'product' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'product', parent: data_get($this->validatedData, 'parent', null), prefix: 'product')),
            default => $response,
        };
    }


    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('scope')) {
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

    public function getTabNavigation(?string $level): array
    {
        $tabs = [
            ['key' => 'department', 'label' => 'Departments'],
            ['key' => 'sub_department', 'label' => 'Sub Departments'],
            ['key' => 'family', 'label' => 'Families'],
            ['key' => 'product', 'label' => 'Products'],
        ];

        if (!$level) {
            return $tabs;
        }

        $order = [
            'department'     => 0,
            'sub_department' => 1,
            'family'         => 2,
            'product'        => 3,
        ];

        if (!isset($order[$level])) {
            return $tabs;
        }

        $index = $order[$level];

        return array_values(
            array_filter($tabs, fn ($tab) => $order[$tab['key']] > $index)
        );
    }

}
