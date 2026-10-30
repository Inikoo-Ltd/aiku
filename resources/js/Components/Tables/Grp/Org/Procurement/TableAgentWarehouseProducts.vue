<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-15 15:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDolly, faBoxCheck, faBoxes, faTruckContainer } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDolly, faBoxCheck, faBoxes, faTruckContainer)

defineProps<{
	data: {}
	kind: "in_production" | "received" | "order_lines"
	tab?: string
}>()

const locale = useLocaleStore()
const organisation = route().params["organisation"]
const productRoute = (slug: string) => route("grp.org.agent.org_supplier_products.show", [organisation, slug])
const supplierRoute = (slug: string) => route("grp.org.agent.org_suppliers.show", [organisation, slug])
const purchaseOrderRoute = (slug: string) => route("grp.org.agent.purchase_orders.show", [organisation, slug])
const containerRoute = (slug: string) => route("grp.org.agent.stock_deliveries.show", [organisation, slug])
const goodsReceiptRoute = (slug: string) => route("grp.org.agent.warehouse.received.goods_receipts.show", [organisation, slug])

const formatQuantity = (value: number | null | undefined) => locale.number(Math.round(Number(value ?? 0) * 1000) / 1000)
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(location)="{ item: row }">
			<div class="flex items-center gap-1.5">
				<Icon :data="row.location_icon" />
				<Link v-if="row.container_slug" :href="containerRoute(row.container_slug)" class="secondaryLink">{{ row.container_reference }}</Link>
				<span v-else class="text-gray-500">{{ ctrans("Warehouse") }}</span>
				<Link
					v-if="row.goods_receipt_slug"
					:href="goodsReceiptRoute(row.goods_receipt_slug)"
					class="secondaryLink whitespace-nowrap text-xs"
					v-tooltip="ctrans('Received on this goods receipt')"
				>
					{{ row.goods_receipt_reference }}
				</Link>
				<span
					v-if="row.is_unchecked"
					v-tooltip="ctrans('Received, not checked for rejects yet')"
					class="whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 ring-1 ring-inset ring-amber-200"
				>
					{{ ctrans("Not checked yet") }}
				</span>
			</div>
		</template>
		<template #cell(image_thumbnail)="{ item: row }">
			<ZoomableImage :src="row.image_thumbnail" :full="row.image_full" :alt="row.code" class="h-12 w-12" />
		</template>
		<template #cell(code)="{ item: row }">
			<Link v-if="row.org_supplier_product_slug" :href="productRoute(row.org_supplier_product_slug)" class="primaryLink whitespace-nowrap">{{ row.code }}</Link>
			<span v-else class="whitespace-nowrap">{{ row.code }}</span>
		</template>
		<template #cell(supplier_code)="{ item: row }">
			<Link v-if="row.org_supplier_slug" :href="supplierRoute(row.org_supplier_slug)" class="secondaryLink">{{ row.supplier_code }}</Link>
			<span v-else>{{ row.supplier_code }}</span>
		</template>
		<template #cell(purchase_order_reference)="{ item: row }">
			<Link v-if="row.purchase_order_slug" :href="purchaseOrderRoute(row.purchase_order_slug)" class="secondaryLink whitespace-nowrap">{{ row.purchase_order_reference }}</Link>
		</template>
		<template #cell(production_started_at)="{ item: row }">
			<span v-if="row.production_started_at" class="whitespace-nowrap">{{ useFormatTime(row.production_started_at) }}</span>
		</template>
		<template #cell(ready_at)="{ item: row }">
			<span v-if="row.ready_at" class="whitespace-nowrap" v-tooltip="row.ready_at_is_approved ? ctrans('Approved ready date') : ctrans('Ready date proposed by the supplier, not approved yet')">
				{{ useFormatTime(row.ready_at) }}<span v-if="!row.ready_at_is_approved" class="text-gray-400"> ({{ ctrans("proposed") }})</span>
			</span>
		</template>
		<template #cell(quantity)="{ item: row }">
			<div class="flex items-center justify-end gap-1.5">
				<span
					v-if="row.quantity_rejected > 0"
					v-tooltip="ctrans(':units units were rejected at our warehouse: the supplier makes them again', { units: formatQuantity(row.quantity_rejected) })"
					class="whitespace-nowrap rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 ring-1 ring-inset ring-red-200"
				>
					{{ ctrans(":units rejected", { units: formatQuantity(row.quantity_rejected) }) }}
				</span>
				<span v-if="row.quantity_ordered && row.quantity_ordered > row.quantity" class="tabular-nums" v-tooltip="ctrans(':received of :ordered units ordered already received', { received: formatQuantity(row.quantity_ordered - row.quantity), ordered: formatQuantity(row.quantity_ordered) })">
					{{ formatQuantity(row.quantity) }} <span class="text-gray-400">/ {{ formatQuantity(row.quantity_ordered) }}</span>
				</span>
				<span v-else class="tabular-nums">{{ formatQuantity(row.quantity) }}</span>
			</div>
		</template>
		<template #cell(skos)="{ item: row }">
			<span class="tabular-nums" v-tooltip="ctrans('1 SKO = :units units', { units: formatQuantity(row.units_per_sko) })">{{ formatQuantity(row.skos) }}</span>
		</template>
	</Table>
</template>
