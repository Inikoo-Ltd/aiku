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
import { faConciergeBell, faGarage, faExclamationTriangle, faPencil } from "@fal";
import { routeType } from "@/types/route";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { onMounted, onUnmounted, ref, inject } from "vue";

import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { Invoice } from "@/types/invoice";
import { RouteParams } from "@/types/route-params";


library.add(faConciergeBell, faGarage, faExclamationTriangle, faPencil);


defineProps<{
    data: {}
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    },
}>();


function productRoute(product: Product) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.products.current_products.index":
            return route(
                "grp.org.shops.show.catalogue.products.current_products.show",
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
        default:
            return null;
    }
}


function organisationRoute(invoice: Invoice) {
    return route(
        "grp.org.overview.products.index",
        [invoice.organisation_slug]);
}

function shopRoute(invoice: Invoice) {
    return route(
        "grp.org.shops.show.catalogue.products.current_products.index",
        [
            invoice.organisation_slug,
            invoice.shop_slug
        ]);
}


const onEditProduct = ref(false);

const isLoadingDetach = ref<string[]>([]);


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
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(organisation_code)="{ item: refund }">
            <Link v-tooltip='refund["organisation_name"]' :href="organisationRoute(refund)" class="secondaryLink">
                {{ refund["organisation_code"] }}
            </Link>
        </template>

        <template #cell(state)="{ item: product }">
            <Icon :data="product.state"></Icon>
        </template>

        <template #cell(price)="{ item: product }">
            {{ locale.currencyFormat(product.currency_code, product.price) }}
        </template>

        <template #cell(code)="{ item: product }">
            <Link :href="productRoute(product) as string" class="primaryLink">
                {{ product["code"] }}
            </Link>
        </template>

        <template #cell(shop_code)="{ item: product }">
            <Link v-if="product['shop_slug']" :href="shopRoute(product) as string" class="secondaryLink">
                {{ product["shop_slug"] }}
            </Link>
        </template>

        <template #cell(type)="{ item: product }">
            <Icon :data="product['type_icon']" />
            <Icon :data="product['state_icon']" />
        </template>

        <template #cell(actions)="{ item }">
            <Link
                v-if="routes?.detach?.name"
                as="button"
                :href="route(routes.detach.name, routes.detach.parameters)"
                :method="routes.detach.method"
                :data="{
                    product: item.id
                }"
                preserve-scroll
                @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)"
            >
                <Button
                    icon="fal fa-times"
                    type="negative"
                    size="xs"
                    :loading="isLoadingDetach.includes('detach' + item.id)"
                />
            </Link>
            <Link
                :v-else="item?.delete_product?.name"
                as="button"
                :href="route(item.delete_product.name, item.delete_product.parameters)"
                :method="item.delete_product.method"
                :data="{
                    product: item.id
                }"
                preserve-scroll
                @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)"
            >
                <Button
                    icon="fal fa-times"
                    type="negative"
                    size="xs"
                    :loading="isLoadingDetach.includes('detach' + item.id)"
                />
            </Link>
        </template>


    </Table>
</template>

