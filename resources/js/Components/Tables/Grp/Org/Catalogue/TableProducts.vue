<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Product } from "@/types/product"
import Icon from "@/Components/Icon.vue"
import { remove as loRemove, cloneDeep} from "lodash-es"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faMinus } from "@fal"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { onMounted, onUnmounted, ref, inject, shallowRef  } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { Invoice } from "@/types/invoice"
import { RouteParams } from "@/types/route-params"
import InputNumber from "primevue/inputnumber"
import { faPlus } from "@far"
import { faXmark } from "@fortawesome/free-solid-svg-icons"
import PureInput from "@/Components/Pure/PureInput.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import Image from "@/Components/Image.vue"



library.add(faOctopusDeploy, faConciergeBell, faGarage, faExclamationTriangle, faPencil)


defineProps<{
    data: {}
    editable_table: boolean
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    },
    isCheckboxProducts?: boolean
    master?: boolean
    selectedProductsId?: {}
}>()

const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()

const editingValues = shallowRef<Record<number, { price: number; rrp: number, unit : string }>>({})
const editingBackup = ref<Record<number, any>>({})
const onEditOpen = ref<number[]>([])
const loadingSave = ref([])

function onEdit(data) {
    const item = cloneDeep(data)
    // backup original values
    editingBackup.value[item.id] = { ...item }

    // make a working copy
    editingValues.value[item.id] = {
        price: item.price,
        rrp: item.rrp,
        unit: item.unit
    }

    if (!onEditOpen.value.includes(item.id)) {
        onEditOpen.value.push(item.id)
    }
}

function onSave(item) {
    const updated = editingValues.value[item.id]

    if (!updated) return

    router.patch(
        route("grp.models.product.update", { product: item.id }),
        {
            price: updated.price,
            rrp: updated.rrp,
            unit: updated.unit
        },
        {
            preserveScroll: true,
            onStart: () => {
                loadingSave.value.push(item.id)
            },
            onSuccess: () => {
                // merge back into the original item so the table updates immediately
                Object.assign(item, updated)

                // cleanup
                loRemove(onEditOpen.value, (id) => id === item.id)
                delete editingBackup.value[item.id]
                delete editingValues.value[item.id]
            },
            onError: (errors) => {
                console.error("Save failed", errors)
            },
            onFinish: () => {
                loRemove(loadingSave.value, (id) => id === item.id)
            }
        }
    )
}

function onCancel(item) {
    if (editingBackup.value[item.id]) {
        Object.assign(item, editingBackup.value[item.id])
    }
    loRemove(onEditOpen.value, (id) => id === item.id)
    delete editingBackup.value[item.id]
    delete editingValues.value[item.id]
}


function productRoute(product: Product) {
    if (!product.slug) {
        return ""
    }

    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.products.current_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.current_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug
                ])
        case "grp.org.shops.show.catalogue.products.orphan_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.orphan_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug
                ])
        case "grp.org.shops.show.catalogue.products.out_of_stock_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.out_of_stock_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug
                ])
        case "grp.org.shops.show.catalogue.products.in_process_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.in_process_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,

                    product.slug])
        case "grp.org.shops.show.catalogue.products.discontinued_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.discontinued_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug])
        case "grp.trade_units.units.show":
            return route(
                "grp.org.shops.show.catalogue.products.all_products.show",
                [
                    product.organisation_slug,
                    product.shop_slug,
                    product.slug])
        case "grp.org.shops.show.catalogue.products.all_products.index":
        case "grp.org.shops.show.catalogue.collections.show":
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.products.all_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug])


        case "grp.org.fulfilments.show.catalogue.index":
            return route(
                "grp.org.fulfilments.show.catalogue.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    product.slug])
        case "grp.org.shops.show.catalogue.departments.show":
        case "grp.org.shops.show.catalogue.departments.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,


                    product.slug])
        case "grp.org.shops.show.catalogue.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).family,

                    product.slug])
        case "grp.org.shops.show.catalogue.departments.show.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,
                    (route().params as RouteParams).family,
                    product.slug
                ])
        case "grp.org.shops.show.catalogue.sub_departments.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).subDepartment,
                    product.slug
                ])
        case "grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).subDepartment,
                    (route().params as RouteParams).family,
                    product.slug
                ])
        case "grp.masters.master_shops.show.master_collections.show":
            return route(
                "grp.masters.master_shops.show.master_products.show",
                [(route().params as RouteParams).masterShop, product.slug])

        case "retina.dropshipping.products.index":
            return route(
                "retina.dropshipping.products.show",
                [product.slug])
        case "retina.dropshipping.portfolios.index":
            return route(
                "retina.dropshipping.portfolios.show",
                [product.slug])
        case "grp.overview.catalogue.products.index":
            return route(
                "grp.org.shops.show.catalogue.products.current_products.show",
                [product.organisation_slug, product.shop_slug, product.slug])

        default:
            if (product.asset_id) {
                return route(
                    "grp.helpers.redirect_asset",
                    [product.asset_id])
            } else return ""

    }
}

function masterProductRoute(product: {}) {
    if (!product.master_product_id) {
        return ""
    }

    return route(
        "grp.helpers.redirect_master_product",
        [product.master_product_id])
}

function organisationRoute(invoice: Invoice) {
    if (!invoice.organisation_slug) {
        return ""
    }

    return route(
        "grp.org.overview.products.index",
        [invoice.organisation_slug])
}

function shopRoute(invoice: Invoice) {
    if (!invoice.organisation_slug || !invoice.shop_slug) {
        //todo fix this
        // return route(
        //     "grp.helpers.redirect_asset",
        //     [invoice.asset_id])
    }
    if (route().current() == "grp.trade_units.units.show") {

        return route(
            "grp.org.shops.show.catalogue.products.all_products.index",
            [
                invoice.organisation_slug,
                invoice.shop_slug
            ])
    }

    return route(
        "grp.org.shops.show.catalogue.dashboard",
        [
            invoice.organisation_slug,
            invoice.shop_slug
        ])
}


const onEditProduct = ref(false)

const isLoadingDetach = ref<string[]>([])


function getMargin(item: ProductItem) {
    const p = Number(item.product?.price)
    const cost = Number(item.product?.org_cost)

    if (isNaN(p) || p === 0) return 0.000
    if (isNaN(cost) || cost === 0) return 100.000

    return Number((((p - cost) / p) * 100).toFixed(1))
}


onMounted(() => {
    if (typeof window !== "undefined") {
        document.addEventListener("keydown", (e) => e.keyCode == 27 ? onEditProduct.value = false : "")
    }
})

onUnmounted(() => {
    document.removeEventListener("keydown", () => false)
})

const locale = inject("locale", aikuLocaleStructure)
const _table = ref<InstanceType<typeof Table> | null>(null)



</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="isCheckboxProducts" key="product-table" ref="_table">
        <template #cell(image_thumbnail)="{ item: product }">
            <div class="flex justify-center">
                <Image :src="product['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>

        <template #cell(organisation_code)="{ item: refund }">
            <Link v-tooltip='refund["organisation_name"]' :href="organisationRoute(refund)" class="secondaryLink">
            {{ refund["organisation_code"] }}
            </Link>
        </template>
        <template #cell(state)="{ item: product }">
            <Icon :data="product.state"></Icon>
        </template>

        <template #cell(name)="{ item: product }">
            <div>
                <ProductUnitLabel
                    v-if="product?.units"
                    :units="product?.units"
                    :unit="product?.unit"
                    class="mr-2"
                />
                {{ product.name }}
            </div>
        </template>

        <template #cell(unit)="{ item: product }">
                <PureInput v-if="onEditOpen.includes(product.id)" :key="product.id" v-model="editingValues[product.id].unit"></PureInput>
                <span v-else>{{ product.unit }}</span>
        </template>

        <template #cell(price)="{ item: product }">
            <div>
                <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].price"
                    mode="currency" :currency="product.currency_code" :step="0.25" showButtons
                    button-layout="horizontal" inputClass="w-full text-xs">
                    <template #incrementbuttonicon>
                        <FontAwesomeIcon :icon="faPlus" />
                    </template>
                    <template #decrementbuttonicon>
                        <FontAwesomeIcon :icon="faMinus" />
                    </template>
                </InputNumber>
                <span v-else>
                    {{ locale.currencyFormat(product.currency_code, product.price) }}
                </span>
            </div>
        </template>

        <template #cell(rrp_per_unit)="{ item: product }">
            {{ locale.currencyFormat(product.currency_code, product.rrp_per_unit) }}

        </template>

        <template #cell(rrp)="{ item: product }">
            <div>
                <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].rrp"
                    mode="currency" :currency="product.currency_code" :step="0.25" showButtons
                    button-layout="horizontal" inputClass="w-full text-xs">
                    <template #incrementbuttonicon>
                        <FontAwesomeIcon :icon="faPlus" />
                    </template>
                    <template #decrementbuttonicon>
                        <FontAwesomeIcon :icon="faMinus" />
                    </template>
                </InputNumber>

                <span v-else>{{ locale.currencyFormat(product.currency_code, product.rrp) }}</span>

            </div>

        </template>


        <template #cell(margin)="{ item }">
            <span :class="{
                'text-green-600 font-medium': getMargin(item) > 0,
                'text-red-600 font-medium': getMargin(item) < 0,
                'text-gray-500': getMargin(item) === 0
            }" class="whitespace-nowrap text-xs inline-block w-16">
                {{ getMargin(item) + "%" }}
            </span>
        </template>

        <template #cell(sales_all)="{ item: product }">
            {{ locale.currencyFormat(product.currency_code, product.sales_all) }}
        </template>

        <template #cell(code)="{ item: product }">
            <div class="whitespace-nowrap">
                <Link :href="(masterProductRoute(product) as string)" v-tooltip="trans('Go to Master')" class="mr-1"
                    :class="[product.master_product_id ? 'opacity-70 hover:opacity-100' : 'opacity-0']">
                <FontAwesomeIcon icon="fab fa-octopus-deploy" color="#4B0082" />
                </Link>
                <Link :href="productRoute(product)" class="primaryLink">
                {{ product["code"] }}
                </Link>
            </div>
        </template>

        <template #cell(shop_code)="{ item: product }">
            <Link v-if="product['shop_slug']" :href="(shopRoute(product) as string)" class="secondaryLink">
            {{ product["shop_code"] }}
            </Link>
        </template>

        <template #cell(type)="{ item: product }">
            <Icon :data="product['type_icon']" />
            <Icon :data="product['state_icon']" />
        </template>

        <template #cell(customers_invoiced_all)="{ item }">
            <Link :href="productRoute(item) + '?tab=customers'" class="secondaryLink">
                {{ item.customers_invoiced_all }}
            </Link>
        </template>

        <template #cell(actions)="{ item }">
            <Link v-if="routes?.detach?.name" as="button" :href="route(routes.detach.name, routes.detach.parameters)"
                :method="routes?.detach?.method" :data="{
                    product: item.id
                }" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
            <Button icon="fal fa-times" type="negative" size="xs"
                :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>
            <Link v-else-if="item?.delete_product?.name" as="button"
                :href="route(item.delete_product.name, item.delete_product.parameters)"
                :method="item?.delete_product?.method" :data="{
                    product: item.id
                }" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
            <Button icon="fal fa-times" type="negative" size="xs"
                :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>

            <div v-if="master || editable_table">
                <button v-if="!onEditOpen.includes(item.id)" class="h-9 align-bottom text-center" @click="()=>onEdit(item)">
                    <FontAwesomeIcon icon="fal fa-pencil" class="h-5 text-gray-500 hover:text-gray-700"
                        aria-hidden="true" v-tooltip="'edit'" />
                </button>

                <span v-else class="flex items-center space-x-3">
                    <Button type="negative" v-tooltip="'cancel'" :icon="faXmark" @click="()=>onCancel(item)" size="sm">
                    </Button>

                    <button class="h-9 align-bottom text-center" :disabled="loadingSave.includes(item.id)"
                        @click="()=>onSave(item)" v-tooltip="'save'">
                        <FontAwesomeIcon v-if="loadingSave.includes(item.id)" icon="fad fa-spinner-third"
                            class="text-2xl animate-spin" fixed-width aria-hidden="true" />

                        <FontAwesomeIcon v-else-if="editingValues[item.id]" icon="fad fa-save" class="h-8"
                            :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />

                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </button>
                </span>
            </div>

        </template>


        <template #checkbox="data">
            <FontAwesomeIcon
                v-if="selectedProductsId[data.data.id]"
                @click="() => emits('selectedRow', { [data.data.id]: false })"
                icon='fas fa-check-square'
                class='text-green-500 p-2 cursor-pointer text-lg mx-auto block'
                fixed-width aria-hidden='true' />
            <FontAwesomeIcon
                v-if="!selectedProductsId[data.data.id]"
                @click="() => emits('selectedRow', { [data.data.id]: true })"
                icon='fal fa-square'
                class='text-gray-500 hover:text-gray-700 p-2 cursor-pointer text-lg mx-auto block'
                fixed-width aria-hidden='true' />
        </template>


        <template #header-checkbox>
            <div></div>
        </template>

    </Table>
</template>
