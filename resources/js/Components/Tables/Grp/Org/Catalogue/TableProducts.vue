<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Product } from "@/types/product";
import Icon from "@/Components/Icon.vue";
import { remove as loRemove } from "lodash-es";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil, faMinus, faSave } from "@fal";
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { onMounted, onUnmounted, ref, inject } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { Invoice } from "@/types/invoice";
import { RouteParams } from "@/types/route-params";
import InputNumber from 'primevue/inputnumber';
import ListItem from "@tiptap/extension-list-item";
import { faCross, faPlus } from "@far";
import { faXmark } from "@fortawesome/free-solid-svg-icons";


library.add(faOctopusDeploy, faConciergeBell, faGarage, faExclamationTriangle, faPencil);


defineProps<{
    data: {}
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    },
    isCheckboxProducts?: boolean
    master?: boolean
}>()

const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()

const editingValues = ref<Record<number, { price: number; rrp: number }>>({})
const editingBackup = ref<Record<number, any>>({})
const onEditOpen = ref<number[]>([])

function onEdit(item) {
    // backup original values
    editingBackup.value[item.id] = { ...item }

    // make a working copy
    editingValues.value[item.id] = {
        price: item.price,
        rrp: item.rrp
    }

    if (!onEditOpen.value.includes(item.id)) {
        onEditOpen.value.push(item.id)
    }
}

function onSave(item) {
    const updated = editingValues.value[item.id]

    // merge back into original item so table updates
    Object.assign(item, updated)

    // 🚀 TODO: send `updated` to API
    console.log("Saving product", item)

    // cleanup
    loRemove(onEditOpen.value, (id) => id === item.id)
    delete editingBackup.value[item.id]
    delete editingValues.value[item.id]
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
        return ''
    }

    switch (route().current()) {
        case "grp.org.shops.show.catalogue.products.current_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.current_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug
                ]);
        case "grp.org.shops.show.catalogue.products.orphan_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.orphan_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug
                ]);
        case "grp.org.shops.show.catalogue.products.in_process_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.in_process_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,

                    product.slug]);
        case "grp.org.shops.show.catalogue.products.discontinued_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.discontinued_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug]);
        case "grp.goods.trade-units.show":
            return route(
                "grp.org.shops.show.catalogue.products.all_products.show",
                [
                    product.organisation_slug,
                    product.shop_slug,
                    product.slug]);
        case "grp.org.shops.show.catalogue.products.all_products.index":
        case "grp.org.shops.show.catalogue.collections.show":
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.products.all_products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    product.slug]);


        case "grp.org.fulfilments.show.catalogue.index":
            return route(
                "grp.org.fulfilments.show.catalogue.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    product.slug]);
        case "grp.org.shops.show.catalogue.departments.show":
        case "grp.org.shops.show.catalogue.departments.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,


                    product.slug]);
        case "grp.org.shops.show.catalogue.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).family,

                    product.slug]);
        case "grp.org.shops.show.catalogue.departments.show.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).department,
                    (route().params as RouteParams).family,
                    product.slug
                ]);
        case "grp.org.shops.show.catalogue.sub_departments.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).subDepartment,
                    product.slug
                ]);
        case "grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index":
            return route(
                "grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).subDepartment,
                    (route().params as RouteParams).family,
                    product.slug
                ]);
        case "grp.masters.master_shops.show.master_collections.show":
            return route(
                "grp.masters.master_shops.show.master_products.show",
                [(route().params as RouteParams).masterShop, product.slug])

        case "retina.dropshipping.products.index":
            return route(
                "retina.dropshipping.products.show",
                [product.slug]);
        case "retina.dropshipping.portfolios.index":
            return route(
                "retina.dropshipping.portfolios.show",
                [product.slug]);
        case "grp.overview.catalogue.products.index":
            return route(
                "grp.org.shops.show.catalogue.products.current_products.show",
                [product.organisation_slug, product.shop_slug, product.slug]);
        /*  case "grp.masters.master_shops.show.master_families.master_products.show":
             return route(
                 "grp.org.shops.show.catalogue.products.current_products.show",
                 [
                     product.organisation_slug,
                     product.shop_slug,
                     product.slug
                 ]); */
        default:
            if (product.asset_id) {
                return route(
                    "grp.helpers.redirect_asset",
                    [product.asset_id]);
            } else return ''

    }
}

function masterProductRoute(product: {}) {
    if (!product.master_product_id) {
        return '';
    }

    return route(
        "grp.helpers.redirect_master_product",
        [product.master_product_id]);
}

function organisationRoute(invoice: Invoice) {
    if (!invoice.organisation_slug) {
        return ''
    }

    return route(
        "grp.org.overview.products.index",
        [invoice.organisation_slug]);
}

function shopRoute(invoice: Invoice) {
    if (!invoice.organisation_slug || !invoice.shop_slug) {
        return route(
            "grp.helpers.redirect_asset",
            [invoice.asset_id]);
    }
    if (route().current() == "grp.goods.trade-units.show") {

        return route(
            "grp.org.shops.show.catalogue.products.all_products.index",
            [
                invoice.organisation_slug,
                invoice.shop_slug,
            ]);
    }

    return route(
        "grp.org.shops.show.catalogue.dashboard",
        [
            invoice.organisation_slug,
            invoice.shop_slug
        ]);
}


const onEditProduct = ref(false);

const isLoadingDetach = ref<string[]>([]);


function getMargin(item: ProductItem) {
    const p = Number(item.product?.price);
    const cost = Number(item.product?.org_cost);

    if (isNaN(p) || p === 0) return 0.000;
    if (isNaN(cost) || cost === 0) return 100.000;

    return Number((((p - cost) / p) * 100).toFixed(1));
}


onMounted(() => {
    if (typeof window !== "undefined") {
        document.addEventListener("keydown", (e) => e.keyCode == 27 ? onEditProduct.value = false : "");
    }
});

onUnmounted(() => {
    document.removeEventListener("keydown", () => false);
});

const locale = inject("locale", aikuLocaleStructure);


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="isCheckboxProducts"
        @onSelectRow="(item) => emits('selectedRow', item)">
        <template #cell(organisation_code)="{ item: refund }">
            <Link v-tooltip='refund["organisation_name"]' :href="organisationRoute(refund)" class="secondaryLink">
            {{ refund["organisation_code"] }}
            </Link>
        </template>
        <template #cell(state)="{ item: product }">
            <Icon :data="product.state"></Icon>
        </template>

        <template #cell(price)="{ item: product }">
            <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].price"
                mode="currency" :currency="product.currency_code" :step="0.25" showButtons button-layout="horizontal"
                inputClass="w-full text-xs">
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
        </template>




        <template #cell(rrp)="{ item: product }">
            <InputNumber v-if="onEditOpen.includes(product.id)" v-model="editingValues[product.id].rrp" mode="currency"
                :currency="product.currency_code" :step="0.25" showButtons button-layout="horizontal"
                inputClass="w-full text-xs">
                <template #incrementbuttonicon>
                    <FontAwesomeIcon :icon="faPlus" />
                </template>
                <template #decrementbuttonicon>
                    <FontAwesomeIcon :icon="faMinus" />
                </template>
            </InputNumber>

            <span v-else>{{ locale.currencyFormat(product.currency_code, product.rrp) }}</span>
        </template>


        <template #cell(margin)="{ item }">
            <span :class="{
                'text-green-600 font-medium': getMargin(item) > 0,
                'text-red-600 font-medium': getMargin(item) < 0,
                'text-gray-500': getMargin(item) === 0
            }" class="whitespace-nowrap text-xs inline-block w-16">
                {{ getMargin(item) + '%' }}
            </span>
        </template>

        <template #cell(sales_all)="{ item: product }">
            {{ locale.currencyFormat(product.currency_code, product.sales_all) }}
        </template>

        <template #cell(code)="{ item: product }">
            <div class="whitespace-nowrap">
                <Link :href="(masterProductRoute(product) as string)" v-tooltip="'Go to Master'" class="mr-1"
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
            <div v-if="master">
                <Button v-if="!onEditOpen.includes(item.id)" type="edit" @click="onEdit(item)" size="xs" />
                <span v-else class="space-x-1">
                    <Button type="negative" :icon="faXmark" @click="onCancel(item)" size="xs" />
                    <Button :icon="faSave" @click="onSave(item)" size="xs" />                  
                </span>
            </div>
        </template>
    </Table>
</template>
