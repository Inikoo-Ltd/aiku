<?php

/*
 * author Arya Permana - Kirin
 * created on 04-06-2025-16h-03m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\IrisAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;

class GetIrisProductCategoryNavigation extends IrisAction
{
    public function handle(Website $website): array
    {
        $shop = $website->shop;
        $domain = $website->domain;
        $data = [];

        $departments = DB::table('product_categories')
            ->where('type', 'department')
            ->where('shop_id', $shop->id)
            ->where('state', ProductCategoryStateEnum::ACTIVE->value)
            ->whereNotNull('webpage_id')
            ->whereNull('deleted_at')
            ->select('id', 'name', 'url')
            ->get();

        foreach ($departments as $department) {
            $departmentUrl = $domain . '/' . $department->url;

            $departmentData = [
                'name' => $department->name,
                'url' => $departmentUrl,
                'sub_departments' => []
            ];

            $subDepartments = DB::table('product_categories')
                ->where('type', 'sub_department')
                ->where('department_id', $department->id)
                ->where('state', ProductCategoryStateEnum::ACTIVE->value)
                ->whereNotNull('webpage_id')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'url')
                ->limit(10)
                ->get();

            foreach ($subDepartments as $subDepartment) {
                $subDepartmentUrl = $departmentUrl . '/' . $subDepartment->url;

                $subDepartmentData = [
                    'name' => $subDepartment->name,
                    'url' => $subDepartmentUrl,
                    'families' => []
                ];

                $families = DB::table('product_categories')
                    ->where('type', 'family')
                    ->where('sub_department_id', $subDepartment->id)
                    ->where('state', ProductCategoryStateEnum::ACTIVE->value)
                    ->whereNotNull('webpage_id')
                    ->select('id', 'name', 'url')
                    ->limit(10)
                    ->get();

                foreach ($families as $family) {
                    $familyUrl = $subDepartmentUrl . '/' . $family->url;

                    $subDepartmentData['families'][] = [
                        'name' => $family->name,
                        'url' => $familyUrl
                    ];
                }

                $departmentData['sub_departments'][] = $subDepartmentData;
            }

            $data[] = $departmentData;
        }

        return $data;
    }

    public function jsonResponse($data): array
    {
        return $data;
    }


}
