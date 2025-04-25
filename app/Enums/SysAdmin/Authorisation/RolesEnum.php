<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 06 Dec 2023 00:32:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Authorisation;

use App\Enums\EnumHelperTrait;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Inventory\Warehouse;
use App\Models\Production\Production;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum RolesEnum: string
{
    use EnumHelperTrait;

    case GROUP_ADMIN = 'group-admin';

    case HUMAN_RESOURCES_SUPERVISOR = 'human-resources-supervisor';
    case HUMAN_RESOURCES_CLERK = 'human-resources-clerk';


    case SYSTEM_ADMIN = 'system-admin';

    case GROUP_WEBMASTER = 'group-webmaster';

    case SUPPLY_CHAIN = 'supply-chain';

    case GOODS_MANAGER = 'goods-manager';

    case MASTERS_MANAGER = 'masters-manager';

    case ORGANISATIONS_MANAGER = 'organisations-manager';


    case ORG_ADMIN = 'org-admin';

    case PROCUREMENT_CLERK = 'procurement-clerk';
    case PROCUREMENT_SUPERVISOR = 'procurement-supervisor';

    case DISPATCH_CLERK = 'dispatch-clerk';
    case DISPATCH_SUPERVISOR = 'dispatch-supervisor';


    case GOODS_IN_CLERK = 'goods-in-clerk';
    case GOODS_IN_SUPERVISOR = 'goods-in-supervisor';

    case ACCOUNTING_CLERK = 'accounting-clerk';
    case ACCOUNTING_SUPERVISOR = 'accounting-supervisor';

    // Shop roles

    case SHOP_ADMIN = 'shop-admin';

    case WEBMASTER_SUPERVISOR = 'webmaster-supervisor';
    case WEBMASTER_CLERK = 'webmaster-clerk';
    case SHOPKEEPER_CLERK = 'shopkeeper-clerk';
    case SHOPKEEPER_SUPERVISOR = 'shopkeeper-supervisor';


    case DISCOUNTS_CLERK = 'discounts-clerk';
    case DISCOUNTS_SUPERVISOR = 'discounts-supervisor';

    case MARKETING_CLERK = 'marketing-clerk';
    case MARKETING_SUPERVISOR = 'marketing-supervisor';


    case CUSTOMER_SERVICE_CLERK = 'customer-service-clerk';
    case CUSTOMER_SERVICE_SUPERVISOR = 'customer-service-supervisor';


    // fulfilment roles

    case FULFILMENT_SHOP_SUPERVISOR = 'fulfilment-shop-supervisor';
    case FULFILMENT_SHOP_CLERK = 'fulfilment-shop-clerk';

    case FULFILMENT_WAREHOUSE_SUPERVISOR = 'fulfilment-warehouse-supervisor';
    case FULFILMENT_WAREHOUSE_WORKER = 'fulfilment-warehouse-worker';

    case WAREHOUSE_ADMIN = 'warehouse-admin';

    case STOCK_CONTROLLER = 'stock-controller';

    // Digital agency roles

    case SEO_SUPERVISOR = 'seo-supervisor';
    case SEO_CLERK = 'seo-clerk';
    case PPC_SUPERVISOR = 'ppc-supervisor';
    case PPC_CLERK = 'ppc-clerk';
    case SOCIAL_SUPERVISOR = 'social-supervisor';
    case SOCIAL_CLERK = 'social-clerk';
    case SAAS_SUPERVISOR = 'saas-supervisor';
    case SAAS_CLERK = 'saas-clerk';

    case MANUFACTURING_ADMIN = 'production-admin';
    case MANUFACTURING_ORCHESTRATOR = 'production-orchestrator';
    case MANUFACTURING_LINE_MANAGER = 'production-line-manager';
    case MANUFACTURING_OPERATOR = 'production-operator';
    case MANUFACTURING_PRODUCT_DEVELOPER = 'production-product-developer';


    public function label(): string
    {
        return match ($this) {
            RolesEnum::GROUP_ADMIN => __('Group admin'),
            RolesEnum::SYSTEM_ADMIN => __('System admin'),
            RolesEnum::GROUP_WEBMASTER => __('Group webmaster'),
            RolesEnum::SUPPLY_CHAIN => __('Supply chain'),
            RolesEnum::PROCUREMENT_CLERK => __('Procurement clerk'),
            RolesEnum::PROCUREMENT_SUPERVISOR => __('Procurement supervisor'),
            RolesEnum::DISPATCH_CLERK => __('Dispatching clerk'),
            RolesEnum::DISPATCH_SUPERVISOR => __('Dispatching supervisor'),
            RolesEnum::ORG_ADMIN => __('Organisation admin'),
            RolesEnum::HUMAN_RESOURCES_CLERK => __('Human resources clerk'),
            RolesEnum::HUMAN_RESOURCES_SUPERVISOR => __('Human resources supervisor'),
            RolesEnum::STOCK_CONTROLLER => __('Stock controller'),
            RolesEnum::ACCOUNTING_CLERK => __('Accounting clerk'),
            RolesEnum::ACCOUNTING_SUPERVISOR => __('Accounting supervisor'),
            RolesEnum::SHOP_ADMIN => __('Shop admin'),
            RolesEnum::FULFILMENT_SHOP_SUPERVISOR => __('Fulfilment supervisor'),
            RolesEnum::FULFILMENT_SHOP_CLERK => __('Fulfilment clerk'),
            RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR => __('Fulfilment warehouse supervisor'),
            RolesEnum::FULFILMENT_WAREHOUSE_WORKER => __('Fulfilment warehouse worker'),
            RolesEnum::WAREHOUSE_ADMIN => __('Warehouse admin'),
            RolesEnum::CUSTOMER_SERVICE_CLERK => __('Customer service clerk'),
            RolesEnum::CUSTOMER_SERVICE_SUPERVISOR => __('Customer service supervisor'),
            RolesEnum::ORGANISATIONS_MANAGER => __('Organisations manager'),
            RolesEnum::GOODS_MANAGER => __('Goods manager'),
            RolesEnum::MASTERS_MANAGER => __('Masters manager'),
            RolesEnum::SEO_SUPERVISOR => __('SEO supervisor'),
            RolesEnum::SEO_CLERK => __('SEO clerk'),
            RolesEnum::PPC_SUPERVISOR => __('PPC supervisor'),
            RolesEnum::PPC_CLERK => __('PPC clerk'),
            RolesEnum::SOCIAL_SUPERVISOR => __('Social supervisor'),
            RolesEnum::SOCIAL_CLERK => __('Social clerk'),
            RolesEnum::SAAS_SUPERVISOR => __('SAAS supervisor'),
            RolesEnum::SAAS_CLERK => __('SAAS clerk'),
            RolesEnum::WEBMASTER_CLERK => __('Webmaster clerk'),
            RolesEnum::WEBMASTER_SUPERVISOR => __('Webmaster supervisor'),
            RolesEnum::SHOPKEEPER_CLERK => __('Shopkeeper clerk'),
            RolesEnum::SHOPKEEPER_SUPERVISOR => __('Shopkeeper supervisor'),
            RolesEnum::DISCOUNTS_CLERK => __('Discounts clerk'),
            RolesEnum::DISCOUNTS_SUPERVISOR => __('Discounts supervisor'),
            RolesEnum::MARKETING_CLERK => __('Marketing clerk'),
            RolesEnum::MARKETING_SUPERVISOR => __('Marketing supervisor'),
            RolesEnum::MANUFACTURING_ADMIN => __('Production admin'),
            RolesEnum::MANUFACTURING_ORCHESTRATOR => __('Production orchestrator'),
            RolesEnum::MANUFACTURING_LINE_MANAGER => __('Production line manager'),
            RolesEnum::MANUFACTURING_OPERATOR => __('Production operator'),
            RolesEnum::MANUFACTURING_PRODUCT_DEVELOPER => __('Production product developer'),
            RolesEnum::GOODS_IN_SUPERVISOR => __('Goods in supervisor'),
            RolesEnum::GOODS_IN_CLERK => __('Goods in clerk'),
        };
    }

    public function getPermissions(): array
    {
        return match ($this) {
            RolesEnum::GROUP_ADMIN => [
                GroupPermissionsEnum::GROUP_REPORTS,
                GroupPermissionsEnum::GROUP_OVERVIEW,
                GroupPermissionsEnum::SYSADMIN,
                GroupPermissionsEnum::SUPPLY_CHAIN,
                GroupPermissionsEnum::ORGANISATIONS,
                GroupPermissionsEnum::GOODS,
                GroupPermissionsEnum::MASTERS,
                GroupPermissionsEnum::GROUP_WEBMASTER

            ],
            RolesEnum::SYSTEM_ADMIN => [
                GroupPermissionsEnum::SYSADMIN
            ],
            RolesEnum::GROUP_WEBMASTER => [
                GroupPermissionsEnum::GROUP_WEBMASTER
            ],
            RolesEnum::SUPPLY_CHAIN => [
                GroupPermissionsEnum::SUPPLY_CHAIN
            ],
            RolesEnum::ORGANISATIONS_MANAGER => [
                GroupPermissionsEnum::ORGANISATIONS
            ],
            RolesEnum::GOODS_MANAGER => [
                GroupPermissionsEnum::GOODS
            ],
            RolesEnum::MASTERS_MANAGER => [
                GroupPermissionsEnum::MASTERS
            ],
            RolesEnum::ORG_ADMIN => [
                OrganisationPermissionsEnum::ORG_ADMIN,
                OrganisationPermissionsEnum::ORG_REPORTS,
                OrganisationPermissionsEnum::PROCUREMENT,
                OrganisationPermissionsEnum::HUMAN_RESOURCES,
                OrganisationPermissionsEnum::SUPERVISOR,
                OrganisationPermissionsEnum::ACCOUNTING,
                OrganisationPermissionsEnum::SUPERVISOR_ACCOUNTING,
                OrganisationPermissionsEnum::INVENTORY,
                OrganisationPermissionsEnum::SEO,
                OrganisationPermissionsEnum::PPC,
                OrganisationPermissionsEnum::SOCIAL,
                OrganisationPermissionsEnum::SAAS

            ],
            RolesEnum::PROCUREMENT_CLERK => [
                OrganisationPermissionsEnum::PROCUREMENT,
                OrganisationPermissionsEnum::INVENTORY,
                WarehousePermissionsEnum::INCOMING,
            ],
            RolesEnum::DISPATCH_CLERK => [
                WarehousePermissionsEnum::LOCATIONS_VIEW,
                WarehousePermissionsEnum::DISPATCHING,
                OrganisationPermissionsEnum::INVENTORY_VIEW
            ],
            RolesEnum::DISPATCH_SUPERVISOR => [
                WarehousePermissionsEnum::LOCATIONS_VIEW,
                WarehousePermissionsEnum::DISPATCHING,
                WarehousePermissionsEnum::SUPERVISOR_DISPATCHING,
                OrganisationPermissionsEnum::INVENTORY_VIEW
            ],
            RolesEnum::GOODS_IN_CLERK => [
                WarehousePermissionsEnum::LOCATIONS_VIEW,
                WarehousePermissionsEnum::INCOMING,
                OrganisationPermissionsEnum::INVENTORY_VIEW
            ],
            RolesEnum::GOODS_IN_SUPERVISOR => [
                WarehousePermissionsEnum::LOCATIONS_VIEW,
                WarehousePermissionsEnum::INCOMING,
                WarehousePermissionsEnum::SUPERVISOR_INCOMING,
                OrganisationPermissionsEnum::INVENTORY_VIEW
            ],
            RolesEnum::HUMAN_RESOURCES_CLERK => [
                OrganisationPermissionsEnum::HUMAN_RESOURCES
            ],
            RolesEnum::HUMAN_RESOURCES_SUPERVISOR => [
                OrganisationPermissionsEnum::HUMAN_RESOURCES,
                OrganisationPermissionsEnum::SUPERVISOR_HUMAN_RESOURCES
            ],
            RolesEnum::ACCOUNTING_CLERK => [
                OrganisationPermissionsEnum::ACCOUNTING,
            ],
            RolesEnum::ACCOUNTING_SUPERVISOR => [
                OrganisationPermissionsEnum::ACCOUNTING,
                OrganisationPermissionsEnum::SUPERVISOR_ACCOUNTING
            ],
            RolesEnum::PROCUREMENT_SUPERVISOR => [
                OrganisationPermissionsEnum::PROCUREMENT,
                OrganisationPermissionsEnum::SUPERVISOR_PROCUREMENT,
                WarehousePermissionsEnum::INCOMING,
            ],
            RolesEnum::SHOP_ADMIN => [
                ShopPermissionsEnum::SHOP_ADMIN,
                ShopPermissionsEnum::PRODUCTS,
                ShopPermissionsEnum::WEB,
                ShopPermissionsEnum::CRM,
                ShopPermissionsEnum::ORDERS,
                ShopPermissionsEnum::DISCOUNTS,
                ShopPermissionsEnum::MARKETING,
                ShopPermissionsEnum::SUPERVISOR_PRODUCTS,
                ShopPermissionsEnum::SUPERVISOR_CRM,
                ShopPermissionsEnum::SUPERVISOR_CRM,
                ShopPermissionsEnum::SUPERVISOR_WEB,
                ShopPermissionsEnum::SUPERVISOR_ORDERS,
                ShopPermissionsEnum::SUPERVISOR_DISCOUNTS,
                ShopPermissionsEnum::SUPERVISOR_MARKETING
            ],
            RolesEnum::FULFILMENT_SHOP_SUPERVISOR => [
                FulfilmentPermissionsEnum::FULFILMENT_SHOP,
                FulfilmentPermissionsEnum::SUPERVISOR_FULFILMENT_SHOP,
                WarehousePermissionsEnum::FULFILMENT,
            ],
            RolesEnum::FULFILMENT_SHOP_CLERK => [
                FulfilmentPermissionsEnum::FULFILMENT_SHOP,
                WarehousePermissionsEnum::FULFILMENT_VIEW,
            ],
            RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR => [
                FulfilmentPermissionsEnum::FULFILMENT_SHOP,
                WarehousePermissionsEnum::FULFILMENT,
            ],
            RolesEnum::FULFILMENT_WAREHOUSE_WORKER => [
                WarehousePermissionsEnum::FULFILMENT,
            ],
            RolesEnum::WAREHOUSE_ADMIN => [
                WarehousePermissionsEnum::LOCATIONS,
                WarehousePermissionsEnum::STOCKS,
                WarehousePermissionsEnum::DISPATCHING,
                WarehousePermissionsEnum::INCOMING,
                WarehousePermissionsEnum::SUPERVISOR_LOCATIONS,
                WarehousePermissionsEnum::SUPERVISOR_STOCKS,
                WarehousePermissionsEnum::SUPERVISOR_DISPATCHING,
                WarehousePermissionsEnum::SUPERVISOR_INCOMING

            ],
            RolesEnum::STOCK_CONTROLLER => [
                WarehousePermissionsEnum::STOCKS,
                WarehousePermissionsEnum::LOCATIONS_VIEW,
            ],
            RolesEnum::SEO_SUPERVISOR => [
                OrganisationPermissionsEnum::SEO,
                OrganisationPermissionsEnum::SUPERVISOR_SEO
            ],
            RolesEnum::SEO_CLERK => [
                OrganisationPermissionsEnum::SEO
            ],
            RolesEnum::PPC_SUPERVISOR => [
                OrganisationPermissionsEnum::PPC,
                OrganisationPermissionsEnum::SUPERVISOR_PPC
            ],
            RolesEnum::PPC_CLERK => [
                OrganisationPermissionsEnum::PPC
            ],
            RolesEnum::SOCIAL_SUPERVISOR => [
                OrganisationPermissionsEnum::SOCIAL,
                OrganisationPermissionsEnum::SUPERVISOR_SOCIAL
            ],
            RolesEnum::SOCIAL_CLERK => [
                OrganisationPermissionsEnum::SOCIAL
            ],
            RolesEnum::SAAS_SUPERVISOR => [
                OrganisationPermissionsEnum::SAAS,
                OrganisationPermissionsEnum::SUPERVISOR_SAAS
            ],
            RolesEnum::SAAS_CLERK => [
                OrganisationPermissionsEnum::SAAS
            ],
            RolesEnum::WEBMASTER_CLERK => [
                ShopPermissionsEnum::WEB,
            ],
            RolesEnum::WEBMASTER_SUPERVISOR => [
                ShopPermissionsEnum::SUPERVISOR_WEB,
            ],
            RolesEnum::SHOPKEEPER_CLERK => [
                ShopPermissionsEnum::PRODUCTS,
                ShopPermissionsEnum::WEB,
                ShopPermissionsEnum::ORDERS,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::DISCOUNTS_VIEW,
                ShopPermissionsEnum::MARKETING_VIEW,

            ],
            RolesEnum::SHOPKEEPER_SUPERVISOR => [
                ShopPermissionsEnum::PRODUCTS,
                ShopPermissionsEnum::SUPERVISOR_PRODUCTS,
                ShopPermissionsEnum::WEB,
                ShopPermissionsEnum::SUPERVISOR_WEB,
                ShopPermissionsEnum::SUPERVISOR_WEB,
                ShopPermissionsEnum::SUPERVISOR_ORDERS,
                ShopPermissionsEnum::ORDERS,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::DISCOUNTS_VIEW,
                ShopPermissionsEnum::MARKETING_VIEW,
            ],
            RolesEnum::DISCOUNTS_CLERK => [
                ShopPermissionsEnum::DISCOUNTS,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::CRM_PROSPECTS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::ORDERS,

            ],
            RolesEnum::DISCOUNTS_SUPERVISOR => [
                ShopPermissionsEnum::DISCOUNTS,
                ShopPermissionsEnum::SUPERVISOR_DISCOUNTS,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::CRM_PROSPECTS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::WEB_EDIT,
                ShopPermissionsEnum::SUPERVISOR_WEB,
            ],
            RolesEnum::MARKETING_CLERK => [
                ShopPermissionsEnum::MARKETING,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::CRM_PROSPECTS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::ORDERS,

            ],
            RolesEnum::MARKETING_SUPERVISOR => [
                ShopPermissionsEnum::MARKETING,
                ShopPermissionsEnum::SUPERVISOR_MARKETING,
                ShopPermissionsEnum::CRM_VIEW,
                ShopPermissionsEnum::CRM_PROSPECTS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::WEB_EDIT,
                ShopPermissionsEnum::SUPERVISOR_WEB,
            ],
            RolesEnum::CUSTOMER_SERVICE_CLERK => [
                ShopPermissionsEnum::CRM,
                ShopPermissionsEnum::ORDERS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::WEB_VIEW,
                ShopPermissionsEnum::DISCOUNTS_VIEW,
                ShopPermissionsEnum::MARKETING_VIEW,

            ],
            RolesEnum::CUSTOMER_SERVICE_SUPERVISOR => [
                ShopPermissionsEnum::CRM,
                ShopPermissionsEnum::SUPERVISOR_CRM,
                ShopPermissionsEnum::ORDERS,
                ShopPermissionsEnum::SUPERVISOR_ORDERS,
                ShopPermissionsEnum::PRODUCTS_VIEW,
                ShopPermissionsEnum::WEB_VIEW,
                ShopPermissionsEnum::DISCOUNTS_VIEW,
                ShopPermissionsEnum::MARKETING_VIEW,

            ],
            RolesEnum::MANUFACTURING_ADMIN => [
                ProductionPermissionsEnum::PRODUCTION_OPERATIONS,
                ProductionPermissionsEnum::PRODUCTION_RD,
                ProductionPermissionsEnum::PRODUCTION_PROCUREMENT,
            ],
            RolesEnum::MANUFACTURING_PRODUCT_DEVELOPER => [
                ProductionPermissionsEnum::PRODUCTION_RD,
            ],
            RolesEnum::MANUFACTURING_ORCHESTRATOR => [
                ProductionPermissionsEnum::PRODUCTION_OPERATIONS,
                ProductionPermissionsEnum::PRODUCTION_PROCUREMENT_VIEW,
            ],
            RolesEnum::MANUFACTURING_LINE_MANAGER => [
                ProductionPermissionsEnum::PRODUCTION_OPERATIONS_EDIT,
                ProductionPermissionsEnum::PRODUCTION_OPERATIONS_VIEW,
                ProductionPermissionsEnum::PRODUCTION_PROCUREMENT_VIEW,
            ],
            RolesEnum::MANUFACTURING_OPERATOR => [
                ProductionPermissionsEnum::PRODUCTION_OPERATIONS_VIEW,
            ],
        };
    }

    public function scope(): string
    {
        return match ($this) {
            RolesEnum::GROUP_ADMIN,
            RolesEnum::SYSTEM_ADMIN,
            RolesEnum::GROUP_WEBMASTER,
            RolesEnum::SUPPLY_CHAIN,
            RolesEnum::GOODS_MANAGER,
            RolesEnum::MASTERS_MANAGER,
            RolesEnum::ORGANISATIONS_MANAGER => 'Group',

            RolesEnum::SHOP_ADMIN,
            RolesEnum::CUSTOMER_SERVICE_CLERK,
            RolesEnum::CUSTOMER_SERVICE_SUPERVISOR,

            RolesEnum::WEBMASTER_CLERK,
            RolesEnum::WEBMASTER_SUPERVISOR,

            RolesEnum::SHOPKEEPER_CLERK,
            RolesEnum::SHOPKEEPER_SUPERVISOR,

            RolesEnum::DISCOUNTS_CLERK,
            RolesEnum::DISCOUNTS_SUPERVISOR,

            RolesEnum::MARKETING_CLERK,
            RolesEnum::MARKETING_SUPERVISOR,

            => 'Shop',
            RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR,
            RolesEnum::FULFILMENT_WAREHOUSE_WORKER,
            RolesEnum::WAREHOUSE_ADMIN,
            RolesEnum::DISPATCH_CLERK,
            RolesEnum::DISPATCH_SUPERVISOR,
            RolesEnum::GOODS_IN_CLERK,
            RolesEnum::GOODS_IN_SUPERVISOR,
            RolesEnum::STOCK_CONTROLLER => 'Warehouse',

            RolesEnum::MANUFACTURING_ADMIN,
            RolesEnum::MANUFACTURING_ORCHESTRATOR,
            RolesEnum::MANUFACTURING_LINE_MANAGER,
            RolesEnum::MANUFACTURING_OPERATOR,
            RolesEnum::MANUFACTURING_PRODUCT_DEVELOPER,


            => 'Production',
            RolesEnum::FULFILMENT_SHOP_SUPERVISOR,
            RolesEnum::FULFILMENT_SHOP_CLERK,
            => 'Fulfilment',
            default => 'Organisation'
        };
    }


    public function scopeTypes(): array
    {
        return match ($this) {
            RolesEnum::FULFILMENT_SHOP_SUPERVISOR,
            RolesEnum::FULFILMENT_SHOP_CLERK,
            RolesEnum::FULFILMENT_WAREHOUSE_SUPERVISOR,
            RolesEnum::FULFILMENT_WAREHOUSE_WORKER,
            RolesEnum::MANUFACTURING_ADMIN,
            RolesEnum::MANUFACTURING_ORCHESTRATOR,
            RolesEnum::MANUFACTURING_LINE_MANAGER,
            RolesEnum::MANUFACTURING_OPERATOR,
            RolesEnum::MANUFACTURING_PRODUCT_DEVELOPER,
            => [OrganisationTypeEnum::SHOP],

            RolesEnum::SEO_SUPERVISOR,
            RolesEnum::SEO_CLERK,
            RolesEnum::PPC_SUPERVISOR,
            RolesEnum::PPC_CLERK,
            RolesEnum::SOCIAL_SUPERVISOR,
            RolesEnum::SOCIAL_CLERK,
            RolesEnum::SAAS_SUPERVISOR,
            RolesEnum::SAAS_CLERK,

            => [OrganisationTypeEnum::DIGITAL_AGENCY],

            RolesEnum::PROCUREMENT_CLERK,
            RolesEnum::PROCUREMENT_SUPERVISOR,
            RolesEnum::DISPATCH_CLERK,
            RolesEnum::DISPATCH_SUPERVISOR,
            RolesEnum::WAREHOUSE_ADMIN,
            RolesEnum::STOCK_CONTROLLER,

            => [OrganisationTypeEnum::AGENT, OrganisationTypeEnum::SHOP],
            RolesEnum::SHOP_ADMIN,

            => [OrganisationTypeEnum::DIGITAL_AGENCY, OrganisationTypeEnum::SHOP],
            default => [OrganisationTypeEnum::DIGITAL_AGENCY, OrganisationTypeEnum::AGENT, OrganisationTypeEnum::SHOP]
        };
    }

    public static function getRolesWithScope(Group|Organisation|Shop|Warehouse|Fulfilment|Production $scope): array
    {
        $roles = array_filter(RolesEnum::cases(), fn ($role) => $role->scope() == class_basename($scope));


        $rolesNames = [];
        foreach ($roles as $case) {
            $skip = false;
            if ($scope instanceof Organisation && !in_array($scope->type, $case->scopeTypes())) {
                $skip = true;
            }
            if (!$skip) {
                $rolesNames[] = self::getRoleName($case->value, $scope);
            }
        }


        return $rolesNames;
    }


    public static function getRoleName(string $rawName, Group|Organisation|Shop|Warehouse|Fulfilment|Production $scope): string
    {
        return match (class_basename($scope)) {
            'Organisation', 'Shop', 'Warehouse', 'Fulfilment', 'Production' => $rawName.'-'.$scope->id,
            default => $rawName
        };
    }


}
