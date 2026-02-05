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
                'navigation' => $this->getTabNavigation($request->query('parent', null)),
            ],
            'data' => $irisCatalogue,
        ]);

        return match ($this->validatedData['scope']) {
            'department' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'department', parent: data_get($this->validatedData, 'parent', null), prefix: 'department')),
            'sub_department' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'sub_department', parent: data_get($this->validatedData, 'parent', null), prefix: 'sub_department')),
            'family' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'family', parent: data_get($this->validatedData, 'parent', null), prefix: 'family')),
            'product' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'product', parent: data_get($this->validatedData, 'parent', null), prefix: 'product')),
            'collection' => $response->table(IndexIrisCatalogue::make()->tableStructure(scope: 'collection', parent: data_get($this->validatedData, 'parent', null), prefix: 'collection')),
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
            'parent_key'         => ['sometimes', 'required_with:parent',
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
            ['key' => 'collection', 'label' => 'Collections'],
        ];

        if (!$level) {
            return $tabs;
        }

        // special rule: if parent = collection → only family + product
        if ($level === 'collection') {
            return array_values(
                array_filter($tabs, fn($tab) => in_array($tab['key'], ['product']))
            );
        }

        $order = [
            'collection'     => 0,
            'department'     => 1,
            'sub_department' => 2,
            'family'         => 3,
            'product'        => 4,
        ];

        if (!isset($order[$level])) {
            return $tabs;
        }

        $index = $order[$level];

        return array_values(
            array_filter($tabs, function ($tab) use ($order, $index) {
                if (!isset($order[$tab['key']])) return false;
                return $order[$tab['key']] > $index;
            })
        );
    }

}
