<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 08 Jun 2025 22:09:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */




/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Catalogue\ProductCategory\DeleteProductCategory;
use App\Actions\Masters\MasterProductCategory\DeleteMasterProductCategory;
use App\Actions\Traits\WithOrganisationSource;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class DeleteAuroraWebProductCategories
{
    use AsAction;
    use WithOrganisationSource;

    private int $count = 0;
    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;

    /**
     * @throws \Throwable
     */
    public function handle(Command $command, Organisation $organisation): void
    {
        $this->setSource($organisation);
        $this->deleteFamilies($organisation);
        $this->deleteDepartments($organisation);




    }

    public function deleteFamilies(Organisation $organisation): void
    {
        $familiesRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->where('Category Subject', 'Product')
            ->get()->pluck('Category Key')->toArray();

        $familiesAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->whereIn('Category Root Key', $familiesRootAuroraIDs)
            ->get()->pluck('Category Key')->toArray();




        $families = DB::table('product_categories')->where('organisation_id', $organisation->id)->whereNotNull('source_family_id')->get()->pluck('source_family_id', 'id');
        foreach ($families as $familyKey => $sourceIDData) {
            $sourceIDData = preg_split('/:/', $sourceIDData);

            if (in_array($sourceIDData[1], $familiesAuroraIDs)) {
                $family = ProductCategory::withTrashed()->find($familyKey);

                if ($family->masterProductCategory) {
                    print 'MF '.$family->masterProductCategory->name." \n";
                    DeleteMasterProductCategory::make()->action($family->masterProductCategory, true);

                }

                print 'F '.$family->name." $family->id  ".$family->source_family_id." WSource:  ".$family->webpage?->source_id." \n";
                DB::table('products')->where('family_id', $family->id)->update(['family_id' => null]);
                DB::table('favourites')->where('family_id', $family->id)->update(['family_id' => null]);
                DB::table('invoice_transactions')->where('family_id', $family->id)->update(['family_id' => null]);


                DeleteProductCategory::make()->action($family, true);
                // dd($department);

            }
        }

    }

    public function deleteDepartments(Organisation $organisation): void
    {
        $departmentsRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->where('Category Subject', 'Category')
            ->get()->pluck('Category Key')->toArray();

        $departmentsAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->whereIn('Category Root Key', $departmentsRootAuroraIDs)
            ->get()->pluck('Category Key')->toArray();




        $departments = DB::table('product_categories')->where('organisation_id', $organisation->id)->whereNotNull('source_department_id')->get()->pluck('source_department_id', 'id');
        foreach ($departments as $departmentKey => $sourceIDData) {
            $sourceIDData = preg_split('/:/', $sourceIDData);

            //$department=ProductCategory::find($departmentKey);



            //dd($sourceIDData[1], $productCategoriesAuroraIDs);
            if (in_array($sourceIDData[1], $departmentsAuroraIDs)) {
                $department = ProductCategory::withTrashed()->find($departmentKey);

                if ($department->masterProductCategory) {
                    print 'MD '.$department->masterProductCategory->name." \n";
                    DeleteMasterProductCategory::make()->action($department->masterProductCategory, true);
                }

                print 'D '.$department->name." $department->id  $department->source_department_id \n";

                DB::table('product_categories')->where('parent_id', $department->id)->update(['parent_id' => null]);
                DB::table('product_categories')->where('department_id', $department->id)->update(['department_id' => null]);

                DB::table('products')->where('department_id', $department->id)->update(['department_id' => null]);
                DB::table('favourites')->where('department_id', $department->id)->update(['department_id' => null]);
                DB::table('invoice_transactions')->where('department_id', $department->id)->update(['department_id' => null]);


                DeleteProductCategory::make()->action($department, true);
                // dd($department);

            }
        }

    }




    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:delete_aurora_web_product_categories {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();


        try {
            $this->handle($command, $organisation);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }



}
