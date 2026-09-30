<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 29 Sep 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { RouteParams } from "@/types/route-params"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
    data: object
    tab?: string
}>()

function productRoute(product: { slug: string }) {
    return route("grp.org.shops.show.catalogue.products.all_products.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).shop,
        product.slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: product }">
            <Link :href="productRoute(product)" class="primaryLink">
                {{ product.code }}
            </Link>
        </template>
        <template #cell(next_order_on)="{ item: product }">
            <span class="whitespace-nowrap">{{ useFormatTime(product.next_order_on) }}</span>
            <span v-if="product.is_due"
                class="ml-2 inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                {{ ctrans("Due") }}
            </span>
        </template>
    </Table>
</template>
