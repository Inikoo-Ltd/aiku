<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { inject, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

type PreOrderLine = {
    pre_order_id: number
    order_reference: string
    order_slug: string
    customer_name: string
    customer_slug: string
    shop_code: string
    shop_slug: string
    product_code: string
    org_stock_code: string
    quantity: number
    ordered_at: string
    supplier_ordered_at: string | null
    dispatch_by: string | null
}

type SupplierGroup = {
    org_supplier_id: number | null
    org_supplier_slug: string | null
    supplier_name: string
    supplier_currency: string | null
    minimum_order: number | null
    order_by_date: string | null
    supplier_cost: number
    meets_minimum: boolean
    number_pre_orders: number
    quantity: number
    sales_value: number
    pre_order_ids: number[]
    not_ordered_ids: number[]
    lines: PreOrderLine[]
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    suppliers: SupplierGroup[]
    currency: string
    can_edit: boolean
    update_route: routeType
}>()

const locale = inject("locale", aikuLocaleStructure)
const openSupplier = ref<number | null>(null)
const isSubmitting = ref(false)

const formatDate = (date: string | null) => date ? new Date(date).toLocaleDateString() : "—"

const submit = (operation: string, preOrderIds: number[], confirmText: string) => {
    if (!window.confirm(confirmText)) {
        return
    }
    router.patch(
        route(props.update_route.name, props.update_route.parameters),
        { operation, pre_order_ids: preOrderIds, cancellation_reason: operation === "cancel" ? "supplier_minimum_not_met" : null },
        {
            preserveScroll: true,
            onStart: () => (isSubmitting.value = true),
            onFinish: () => (isSubmitting.value = false),
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="!suppliers.length" class="p-6 text-center text-gray-500">
        {{ ctrans("No pre-orders are waiting for goods.") }}
    </div>

    <div v-else class="p-4 space-y-3">
        <div v-for="(supplier, index) in suppliers" :key="supplier.org_supplier_id ?? 'none'" class="rounded border border-gray-200">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 cursor-pointer hover:bg-gray-50" @click="openSupplier = openSupplier === index ? null : index">
                <div class="font-semibold min-w-48">{{ supplier.supplier_name }}</div>
                <div class="text-sm">{{ ctrans(":count pre-orders", { count: String(supplier.number_pre_orders) }) }}</div>
                <div class="text-sm">{{ ctrans("Quantity") }}: <b>{{ supplier.quantity }}</b></div>
                <div class="text-sm">{{ ctrans("Sales value") }}: <b>{{ locale.currencyFormat(currency, supplier.sales_value) }}</b></div>
                <div class="text-sm">
                    {{ ctrans("At supplier cost (approx.)") }}:
                    <b>{{ supplier.supplier_currency ? locale.currencyFormat(supplier.supplier_currency, supplier.supplier_cost) : supplier.supplier_cost }}</b>
                    <span v-if="supplier.minimum_order !== null" :class="supplier.meets_minimum ? 'text-green-700' : 'text-red-700'">
                        / {{ ctrans("minimum") }} {{ supplier.supplier_currency ? locale.currencyFormat(supplier.supplier_currency, supplier.minimum_order) : supplier.minimum_order }}
                    </span>
                </div>
                <div class="text-sm">{{ ctrans("Order by") }}: <b>{{ formatDate(supplier.order_by_date) }}</b></div>
                <div v-if="can_edit" class="ml-auto flex gap-2" @click.stop>
                    <Button v-if="supplier.not_ordered_ids.length" size="xs" type="secondary" :loading="isSubmitting"
                        :label="ctrans('Mark supplier ordered')"
                        @click="submit('supplier_ordered', supplier.not_ordered_ids, ctrans('Mark these pre-orders as ordered from the supplier? Trade customers then lose the made-to-order deposit if they cancel.'))" />
                    <Button size="xs" type="negative" :loading="isSubmitting"
                        :label="ctrans('Cancel all, full refund')"
                        @click="submit('cancel', supplier.pre_order_ids, ctrans('Cancel these pre-orders because the supplier minimum was not met? Every customer gets a full refund.'))" />
                </div>
            </div>

            <table v-if="openSupplier === index" class="w-full text-sm border-t border-gray-200">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ ctrans("Order") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Shop") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Customer") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Product") }}</th>
                        <th class="px-4 py-2">{{ ctrans("SKO") }}</th>
                        <th class="px-4 py-2 text-right">{{ ctrans("Quantity") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Ordered") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Supplier ordered") }}</th>
                        <th class="px-4 py-2">{{ ctrans("Dispatch by") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in supplier.lines" :key="line.pre_order_id + line.product_code" class="border-t border-gray-100">
                        <td class="px-4 py-1.5">
                            <Link :href="route('grp.org.shops.show.ordering.orders.show', [route().params.organisation, line.shop_slug, line.order_slug])" class="primaryLink">{{ line.order_reference }}</Link>
                        </td>
                        <td class="px-4 py-1.5">{{ line.shop_code }}</td>
                        <td class="px-4 py-1.5">{{ line.customer_name }}</td>
                        <td class="px-4 py-1.5">{{ line.product_code }}</td>
                        <td class="px-4 py-1.5">{{ line.org_stock_code }}</td>
                        <td class="px-4 py-1.5 text-right">{{ line.quantity }}</td>
                        <td class="px-4 py-1.5">{{ formatDate(line.ordered_at) }}</td>
                        <td class="px-4 py-1.5">{{ formatDate(line.supplier_ordered_at) }}</td>
                        <td class="px-4 py-1.5">{{ formatDate(line.dispatch_by) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
