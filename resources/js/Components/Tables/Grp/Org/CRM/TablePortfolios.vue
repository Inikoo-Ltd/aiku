<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FulfilmentCustomer } from "@/types/Customer"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"

import { faTrashAlt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTrashAlt)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()


function shopRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "shops.show.customers.index":
            return route(
                "shops.show",
                [customer.shop_slug])
        default:
            return route(
                "shops.show",
                [customer.shop_slug])
    }
}

function dropshipRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.customers.show.portfolios.index":
            return route(
                "grp.org.shops.show.catalogue.products.all_products.show",
                [route().params['organisation'], route().params['shop'], customer.slug])
        default:
            return ""
    }
}

const isDeleteLoading = ref<boolean | string>(false)
const onDeletePortfolio = async (routeDelete: routeType, custSlug: string) => {
    isDeleteLoading.value = custSlug
    try {
        router[routeDelete.method || 'get'](route(routeDelete.name, routeDelete.parameters))
        notify({
            title: 'Success',
            text: `Portfolio ${custSlug} has been deleted`,
            type: 'success',
        })
    } catch {
        notify({
            title: 'Something went wrong.',
            // text: 'Portfolio has been deleted',
            type: 'error',
        })
    }
}

</script>

<template>
    <!-- <pre>{{ data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(product_code)="{ item: customer }">
            <Link :href="dropshipRoute(customer)" class="primaryLink">
            {{ customer["product_code"] }}
            </Link>
        </template>

        <!-- Column: Product -->
        <template #cell(shop)="{ item: customer }" class="primaryLink">
            <Link :href="shopRoute(customer)">
            {{ customer["shop"] }}
            </Link>
        </template>

        <!-- Column: Customer Reference -->
        <template #cell(location)="{ item: customer }">
            <AddressLocation :data="customer['location']" />
        </template>

        <!-- Column: Created At -->
        <template #cell(created_at)="{ item: customer }">
            <div class="text-gray-500">{{ useFormatTime(customer["created_at"], {
                localeCode: locale.language.code,
                formatTime: "Ymd" }) }}</div>
        </template>

        <!-- Column: Action (delete) -->
        <template #cell(action)="{ item: customer }">
            <Button @click="() => onDeletePortfolio(customer.routes.delete_route, customer.slug)" :key="customer.slug"
                icon="fal fa-trash-alt" type="negative" :disabled="isDeleteLoading === customer.slug"
                :loading="isDeleteLoading === customer.slug" />
        </template>
    </Table>
</template>
