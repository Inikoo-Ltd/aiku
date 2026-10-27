<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDolly, faBoxCheck, faBoxes } from "@fal"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"

library.add(faDolly, faBoxCheck, faBoxes)

defineProps<{
	data: {}
	tab?: string
}>()

const organisation = route().params["organisation"]
const productRoute = (slug: string) => route("grp.org.agent.org_supplier_products.show", [organisation, slug])
const supplierRoute = (slug: string) => route("grp.org.agent.org_suppliers.show", [organisation, slug])
const purchaseOrderRoute = (slug: string) => route("grp.org.agent.purchase_orders.show", [organisation, slug])
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(state)="{ item: stock }"><Icon :data="stock.state_icon" /></template>
		<template #cell(image_thumbnail)="{ item: stock }">
			<ZoomableImage :src="stock.image_thumbnail" :full="stock.image_full" :alt="stock.code" class="h-12 w-12" />
		</template>
		<template #cell(code)="{ item: stock }">
			<Link v-if="stock.org_supplier_product_slug" :href="productRoute(stock.org_supplier_product_slug)" class="primaryLink whitespace-nowrap">{{ stock.code }}</Link>
			<span v-else class="whitespace-nowrap">{{ stock.code }}</span>
		</template>
		<template #cell(supplier_code)="{ item: stock }">
			<Link v-if="stock.org_supplier_slug" :href="supplierRoute(stock.org_supplier_slug)" class="secondaryLink">{{ stock.supplier_code }}</Link>
			<span v-else>{{ stock.supplier_code }}</span>
		</template>
		<template #cell(purchase_order_reference)="{ item: stock }">
			<Link v-if="stock.purchase_order_slug" :href="purchaseOrderRoute(stock.purchase_order_slug)" class="secondaryLink">{{ stock.purchase_order_reference }}</Link>
		</template>
		<template #cell(quantity)="{ item: stock }"><span class="tabular-nums">{{ stock.quantity.toLocaleString() }}</span></template>
	</Table>
</template>
