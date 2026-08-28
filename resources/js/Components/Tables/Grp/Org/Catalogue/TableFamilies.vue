<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Family } from "@/types/family"
import Icon from "@/Components/Icon.vue"
import { inject, ref } from "vue"
import { routeType } from "@/types/route"
import { remove as loRemove } from 'lodash-es'
import Button from "@/Components/Elements/Buttons/Button.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCheck, faTimesCircle, faCheckCircle, faBroadcastTower, faSkull } from "@fal";
import { faTriangle, faEquals, faMinus } from "@fas"
import { RouteParams } from "@/types/route-params";
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@common/Components/Image.vue"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"

library.add(faCheck, faOctopusDeploy)

defineProps<{
    data: object
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    isCheckBox?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)

const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()

function redirectProductCategoryRoute(family: Family) {
    return route('grp.majordomo.redirect_product_category', [family.id])
}

function familyInShopRoute(family: Family) {
    if (!family.organisation_slug || !family.shop_slug) {
        return redirectProductCategoryRoute(family)
    }

    return route(
        'grp.org.shops.show.catalogue.families.show',
        [family.organisation_slug, family.shop_slug, family.slug])
}

function familyRoute(family: Family) {
    switch (route().current()) {
        case "grp.shops.show":
        case "grp.org.shops.show.catalogue.families.sales":
        case "grp.org.shops.show.catalogue.families.index":
        case "grp.org.shops.show.catalogue.families.no_department.index":
        case "grp.org.shops.show.catalogue.collections.show":
            return route(
                "grp.org.shops.show.catalogue.families.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, family.slug])
        case "grp.org.shops.show.catalogue.departments.show":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).department, family.slug])
        case 'grp.org.shops.index':
            return family.shop_slug
                ? route(
                    "grp.org.shops.show.catalogue.families.show",
                    [(route().params as RouteParams).organisation, family.shop_slug, family.slug])
                : redirectProductCategoryRoute(family)
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.families.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, family.slug])
        case "grp.org.shops.show.catalogue.departments.show.families.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).department, family.slug])
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).department, (route().params as RouteParams).subDepartment, family.slug])
        case "grp.org.shops.show.catalogue.sub_departments.show.families.index":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.families.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).subDepartment, family.slug])
        case "grp.masters.master_shops.show.master_collections.show":
            return route(
                "grp.masters.master_shops.show.master_families.show",
                [(route().params as RouteParams).masterShop, family.slug])
        case 'grp.overview.catalogue.families.index':
            return familyInShopRoute(family)
        case 'grp.masters.master_shops.show.master_departments.show.master_families.show':
        case 'grp.masters.master_shops.show.master_families.show':
        case 'grp.masters.master_shops.show.master_sub_departments.master_families.show':
        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.show':
        case "grp.masters.master_shops.show.master_families.families":
        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families':
            return familyInShopRoute(family)
        default:
            return redirectProductCategoryRoute(family)
    }
}

function shopRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.index':
            return family.shop_slug
                ? route(
                    "grp.org.shops.show.catalogue.dashboard",
                    [(route().params as RouteParams).organisation, family.shop_slug])
                : undefined
        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families':
            return family.organisation_slug && family.shop_slug
                ? route(
                    "grp.org.shops.show.catalogue.families.show",
                    [family.organisation_slug, family.shop_slug, family.slug])
                : undefined
        default:
            return family.organisation_slug && family.shop_slug
                ? route(
                    "grp.org.shops.show.catalogue.dashboard",
                    [family.organisation_slug, family.shop_slug])
                : undefined
    }
}
function productRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.show.catalogue.departments.show.families.index':
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show.products.index",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).department, family.slug])
        case 'grp.org.shops.show.catalogue.families.sales':
        case 'grp.org.shops.show.catalogue.families.index':
            return route(
                "grp.org.shops.show.catalogue.families.show.products.index",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, family.slug])
    }
}

function departmentRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.index':
            return route(
                "grp.org.shops.show.catalogue.departments.index",
                [(route().params as RouteParams).organisation, family.shop_slug, family.department_slug])
        case 'grp.org.shops.show.catalogue.dashboard':
        case 'grp.org.shops.show.catalogue.families.sales':
        case 'grp.org.shops.show.catalogue.families.index':
            return route(
                "grp.org.shops.show.catalogue.departments.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, family.department_slug])
        case 'grp.masters.master_shops.show.master_families.show':
        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families':
            return family.organisation_slug && family.shop_slug
                ? route(
                    "grp.org.shops.show.catalogue.departments.show",
                    [family.organisation_slug, family.shop_slug, family.department_slug])
                : undefined
    }
}

function collectionRoute(organisation_slug: string, shop_slug: string, collection: { id: string, name: string, slug: string, code?: string }) {
    const organisation = route().current() === 'xxxxxxxxx'
        ? (route().params as RouteParams).organisation
        : organisation_slug

    if (!organisation || !shop_slug || !collection.slug) {
        return undefined
    }

    return route(
        "grp.org.shops.show.catalogue.collections.show",
        {
            organisation: organisation,
            shop: shop_slug,
            collection: collection.slug
        })
}

function subDepartmentRoute(family: Family) {
    const current = route().current()
    const params = route().params as RouteParams

    switch (current) {
        case 'grp.org.shops.show.catalogue.families.sales':
        case 'grp.org.shops.show.catalogue.families.index':
        case 'grp.org.shops.show.catalogue.departments.show.families.index':
            return route(
                'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                [
                    params.organisation,
                    params.shop,
                    family.department_slug,
                    family.sub_department_slug
                ]
            )

        case 'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.families':
            return family.organisation_slug && family.shop_slug
                ? route(
                    'grp.org.shops.show.catalogue.sub_departments.show',
                    [
                        family.organisation_slug,
                        family.shop_slug,
                        family.sub_department_slug
                    ]
                )
                : undefined
    }
}
function masterFamilyRoute(family: Family) {
    if (!family.master_product_category_id) {
        return '';
    }

    return route(
        "grp.majordomo.redirect_master_product_category",
        [family.master_product_category_id]);
}

function offerRoute(family: Family) {
    if (!family.organisation_slug || !family.shop_slug || !family.last_offer?.slug) {
        return undefined
    }

    return route(
        "grp.org.shops.show.discounts.offers.show",
        [family.organisation_slug, family.shop_slug, family.last_offer.slug])
}

const offerDate = (date?: string | null) => date
    ? useFormatTime(date, { localeCode: locale.language.code, formatTime: 'dd MMM yy' })
    : trans('No date')

const isLoadingDetach = ref<string[]>([])

const dotClass = (filled: boolean) =>
    filled ? "bg-green-100 text-green-600" : "bg-red-100 text-red-600";
const statusIcon = (filled: boolean) => (filled ? faCheckCircle : faTimesCircle);

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return {
            icon: faTriangle
        }
    } else if (!isPositive) {
        return {
            icon: faTriangle,
            class: 'rotate-180'
        }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    if (isPositive) {
        return 'text-green-500'
    } else if (!isPositive) {
        return 'text-red-500'
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="isCheckBox"
        @onSelectRow="(item) => emits('selectedRow', item)">

        <template #cell(image_thumbnail)="{ item: product }">
            <div class="flex justify-center items-center w-8 aspect-square rounded-full overflow-hidden shadow">
                <Image :src="product['image_thumbnail']" />
            </div>
        </template>

        <template #cell(state)="{ item: family }">
            <Icon :data="family.state" />
        </template>

        <template #cell(code)="{ item: family }">
            <div class="whitespace-nowrap">
                <Link :href="(masterFamilyRoute(family) as string)" v-tooltip="trans('Go to Master')" class="mr-1"
                    :class="[family.master_product_category_id ? 'opacity-70 hover:opacity-100' : 'opacity-0']">
                <FontAwesomeIcon icon="fab fa-octopus-deploy" color="#4B0082" />
                </Link>

                <Link :href="familyRoute(family)" class="primaryLink" v-tooltip="family.name">
                {{ family["code"] }}
                </Link>
            </div>
        </template>

        <template #cell(shop_code)="{ item: family }">
            <Link v-if="shopRoute(family)" :href="(shopRoute(family) as string)" class="secondaryLink">
            {{ family["shop_code"] }}
            </Link>
            <div v-else>
                {{ family["shop_code"] }}
            </div>
        </template>

        <template #cell(gr_detail)="{ item: family }">
            <div v-if="family.gr_detail && (family.gr_detail.percentage || family.gr_detail.quantity)"
                class="whitespace-nowrap tabular-nums">
                <span v-tooltip="trans('Percentage off')">{{ family.gr_detail.percentage }}%</span>
                <span class="text-gray-400 mx-1">·</span>
                <span v-tooltip="trans('Trigger quantity')">{{ family.gr_detail.quantity }}</span>
            </div>
            <span v-else class="text-gray-400 italic">-</span>
        </template>

        <template #cell(current_products)="{ item: family }">
            <Link v-if="productRoute(family)" :href="(productRoute(family) as string)" class="primaryLink">
            {{ family["current_products"] }}
            </Link>
            <div v-else>
                {{ family["current_products"] }}
            </div>
        </template>

        <!-- Column: Department code -->
        <template #cell(department_code)="{ item: family }">
            <Link v-if="family.department_slug && departmentRoute(family)" :href="(departmentRoute(family) as string)"
                class="secondaryLink">
            {{ family["department_code"] }}
            </Link>

            <div v-else>
                {{ family["department_code"] }}
            </div>
        </template>

        <!-- Column: Department name -->
        <template #cell(department_name)="{ item: family }">
            <Link v-if="family.department_slug && departmentRoute(family)" :href="(departmentRoute(family) as string)"
                class="secondaryLink">
            {{ family["department_name"] }}
            </Link>
            <div v-else>
                {{ family["department_name"] }}
            </div>
        </template>

        <!-- Column: Collections -->
        <template #cell(collections)="{ item: family }">
            <div class="flex flex-col gap-2">
                <ul>
                    <li v-for="collect in family.collections" :key="collect.id" class="list-disc">
                        <Link v-if="collectionRoute(family.organisation_slug, family.shop_slug, collect)"
                            :href="(collectionRoute(family.organisation_slug, family.shop_slug, collect) as string)"
                            class="secondaryLink w-fit">
                        {{ collect.name }} <span v-if="collect.code" class="text-gray-400 italic">({{ collect.code
                            }})</span>
                        </Link>
                        <span v-else>
                            {{ collect.name }} <span v-if="collect.code" class="text-gray-400 italic">({{ collect.code
                                }})</span>
                        </span>
                    </li>
                </ul>
            </div>
        </template>

        <template #cell(product_categories)="{ item: family }">
            <!-- <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
                {{ family["department_code"] }}
            </Link> -->
        </template>

        <template #cell(sub_department_name)="{ item: family }">
            <Link v-if="family.sub_department_slug && subDepartmentRoute(family)"
                :href="(subDepartmentRoute(family) as string)" class="secondaryLink">
            {{ family["sub_department_code"] }}
            </Link>
            <span v-else-if="family.sub_department_code">{{ family["sub_department_code"] }}</span>
            <span v-else class="text-xs text-gray-400 italic">-</span>
        </template>

        <template #cell(actions)="{ item }">
            <Link v-if="routes?.detach?.name" as="button" :href="route(routes.detach.name, routes.detach.parameters)"
                :method="routes.detach.method" :data="{
                    family: item.id
                }" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
            <Button icon="fal fa-times" type="negative" size="xs"
                :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>
        </template>

        <template #cell(action)="{ item }">
            <Link v-if="routes?.detach?.name" as="button"
                :href="route(routes.detach.name, { ...routes.detach.parameters, family: item.id })"
                :method="routes.detach.method" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
            <Button icon="fal fa-times" type="negative" size="xs"
                :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>
        </template>

        <template #cell(webpage_state)="{ item: family }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-if="family['webpage_state'] == 'live'" v-tooltip="trans('Webpage is Live')" :icon="faBroadcastTower" class="text-green-500"/>
                <FontAwesomeIcon v-else v-tooltip="trans('Webpage is Offline')" :icon="faSkull" class="text-red-500"/>
            </div>
        </template>

        <template #cell(is_following_master)="{ item: family }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-tooltip="family.is_following_master ? ctrans('Family is Following Master') : ctrans('Family is not Following Master')" 
                    :icon="faOctopusDeploy" :class="family.is_following_master ? 'text-green-500' : 'text-red-500'"
                />
            </div>
        </template>

        <template #cell(is_name_reviewed)="{ item }">
            <div class="flex">
                <FontAwesomeIcon 
                    :class="[
                        'flex items-center justify-center w-4 h-4 rounded-full my-auto',
                        dotClass(item.is_name_reviewed),
                    ]" 
                    :icon="statusIcon(item.is_name_reviewed)" 
                    v-tooltip="!item.is_name_reviewed ? ctrans('Name needs a review') : ''" 
                />
                
                <span class="ml-auto text-right" v-if="!item.is_name_reviewed && item.name_updated_at">
                    {{ ctrans('Master updated at') }}: <br>
                    {{ useFormatTime(item.name_updated_at, { formatTime: 'hm' }) }}
                </span>
            </div>
        </template>

        <template #cell(is_description_reviewed)="{ item }">
            <div class="flex">
                <FontAwesomeIcon 
                    :class="[
                        'flex items-center justify-center w-4 h-4 rounded-full my-auto',
                        dotClass(item.is_description_reviewed),
                    ]" 
                    :icon="statusIcon(item.is_description_reviewed)" 
                    v-tooltip="!item.is_description_reviewed ? ctrans('Description needs a review') : ''" 
                />
                
                <span class="ml-auto text-right" v-if="!item.is_description_reviewed && item.description_updated_at">
                    {{ ctrans('Master updated at') }}: <br>
                    {{ useFormatTime(item.description_updated_at, { formatTime: 'hm' }) }}
                </span>
            </div>
        </template>

        <template #cell(is_description_title_reviewed)="{ item }">
            <div class="flex">
                <FontAwesomeIcon 
                    :class="[
                        'flex items-center justify-center w-4 h-4 rounded-full my-auto',
                        dotClass(item.is_description_title_reviewed),
                    ]" 
                    :icon="statusIcon(item.is_description_title_reviewed)" 
                    v-tooltip="!item.is_description_title_reviewed ? ctrans('Description Title needs a review') : ''" 
                />
                
                <span class="ml-auto text-right" v-if="!item.is_description_title_reviewed && item.description_title_updated_at">
                    {{ ctrans('Master updated at') }}: <br>
                    {{ useFormatTime(item.description_title_updated_at, { formatTime: 'hm' }) }}
                </span>
            </div>
        </template>

        <template #cell(is_description_extra_reviewed)="{ item }">
            <div class="flex">
                <FontAwesomeIcon 
                    :class="[
                        'flex items-center justify-center w-4 h-4 rounded-full my-auto',
                        dotClass(item.is_description_extra_reviewed),
                    ]" 
                    :icon="statusIcon(item.is_description_extra_reviewed)" 
                    v-tooltip="!item.is_description_extra_reviewed ? ctrans('Extra Description needs a review') : ''" 
                />
                
                <span class="ml-auto text-right" v-if="!item.is_description_extra_reviewed && item.extra_description_updated_at">
                    {{ ctrans('Master updated at') }}: <br>
                    {{ useFormatTime(item.extra_description_updated_at, { formatTime: 'hm' }) }}
                </span>
            </div>
        </template>

        <template #cell(last_offer)="{ item: family }">
            <div v-if="family.last_offer" class="flex items-center whitespace-nowrap text-xs">
                <span v-if="family.offer_freshness" v-tooltip="family.offer_freshness.tooltip"
                    class="mr-1.5 h-2 w-2 shrink-0 rounded-full" :class="family.offer_freshness.class" />
                <Link v-if="offerRoute(family)" :href="(offerRoute(family) as string)" class="secondaryLink"
                    v-tooltip="family.last_offer.name">
                {{ family.last_offer.slug }}
                </Link>
                <span v-else v-tooltip="family.last_offer.name">{{ family.last_offer.slug }}</span>
                <span class="text-gray-400 mx-1">·</span>
                <span v-tooltip="trans('Offer date')">{{ offerDate(family.last_offer.start_at) }}</span>
                <span class="text-gray-400 mx-1">→</span>
                <span v-tooltip="trans('Expiration date')">
                    {{ family.last_offer.end_at ? offerDate(family.last_offer.end_at) : trans('No expiration') }}
                </span>
            </div>
            <div v-else class="flex items-center whitespace-nowrap text-xs">
                <span v-if="family.offer_freshness" v-tooltip="family.offer_freshness.tooltip"
                    class="mr-1.5 h-2 w-2 shrink-0 rounded-full" :class="family.offer_freshness.class" />
                <span class="text-gray-400 italic">{{ trans('Never') }}</span>
            </div>
        </template>

        <template #cell(sold)="{ item }">
            <div class="inline" v-tooltip="'Number if outers sold'">{{ item.sold }}</div>
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            {{ locale.currencyFormat(item.grp_currency_code ?? item.currency_code, item.sales_grp_currency_external) }}
        </template>

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta">
                <span>{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

    </Table>
</template>
