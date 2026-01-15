<?php

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\Iris\Catalogue\IndexIrisDepartments;
use App\Actions\Iris\Catalogue\IndexIrisFamilies;
use App\Actions\Iris\Catalogue\IndexIrisSubDepartments;
use App\Http\Resources\Catalogue\IrisDepartmentResource;
use App\Http\Resources\Catalogue\IrisFamilyResource;
use App\Http\Resources\Catalogue\IrisSubDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use Illuminate\Database\Eloquent\Model;

class ShowIrisCatalogue
{
    use AsAction;
    public $context;

    public function asController(ActionRequest $request): array
    {
        return $this->handle($request);
    }


    public function handle(ActionRequest $request): array
    {
        $website = $request->get('website');

       

        $scope     = $request->query('scope', 'departments');
        $parent    = $request->query('parent');
        $parentKey = $request->query('parent_key');

        $this->context = $website->shop;

        if ($parent && $parentKey) {
            $this->context = $this->resolveParent(
                $parent,
                (int) $parentKey,
                $website->shop_id
            );
        }

        return [
            'scope'           => $scope,
            'parent'          => $parent,
            'parent_key'      => $parentKey,
            'departments'     => IndexIrisDepartments::run($this->context),
            'sub_departments' => IndexIrisSubDepartments::run($this->context),
            'families'        => IndexIrisFamilies::run($this->context),
            'products'        => collect(),
            'collections'     => collect(),
        ];
    }




    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        $response = Inertia::render('Catalogue/CatalogueIris', [
            'tabs' => [
                'current' => $data['scope'],
                'navigation' => [
                    ['key' => 'departments', 'label' => 'Departments'],
                    ['key' => 'sub_departments', 'label' => 'Sub Departments'],
                    ['key' => 'families', 'label' => 'Families'],
                    ['key' => 'products', 'label' => 'Products'],
                ],
            ],

            'data'            => [
                    'departments'     => IrisDepartmentResource::collection($data['departments']),
                    'sub_departments' => IrisSubDepartmentResource::collection($data['sub_departments']),
                    'families'        => IrisFamilyResource::collection($data['families']),
                    'collections'     => $data['collections'],
                    'products'        => $data['products'],
            ]
          
        ]);

        return match ($data['scope']) {
            'departments' => $response->table(IndexIrisDepartments::make()->tableStructure($this->context, prefix: 'departments')),
            'sub_departments' =>$response->table(IndexIrisSubDepartments::make() ->tableStructure(prefix: 'sub_departments')),
            'families' => $response->table(IndexIrisFamilies::make()->tableStructure($this->context, prefix: 'families')),
            default => $response,
        };
    }

    public function resolveParent(string $parent, int $parentKey, int $shopId): Model
    {
        $type = match ($parent) {
            'department'     => ProductCategoryTypeEnum::DEPARTMENT,
            'sub_department' => ProductCategoryTypeEnum::SUB_DEPARTMENT,
            'family'         => ProductCategoryTypeEnum::FAMILY,
            default          => abort(404),
        };

        return ProductCategory::query()
            ->where('shop_id', $shopId)
            ->where('type', $type)
            ->findOrFail($parentKey);
    }
}
