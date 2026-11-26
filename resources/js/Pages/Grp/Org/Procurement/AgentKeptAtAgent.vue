<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { faTruckContainer } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PurchaseOrderLineAddToContainerDialog from "@/Components/Procurement/PurchaseOrderLineAddToContainerDialog.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: object
}>()

const locale = useLocaleStore()
const organisation = route().params["organisation"]
const productRoute = (slug: string) => route("grp.org.agent.org_supplier_products.show", [organisation, slug])
const supplierRoute = (slug: string) => route("grp.org.agent.org_suppliers.show", [organisation, slug])
const purchaseOrderRoute = (slug: string) => route("grp.org.agent.purchase_orders.show", [organisation, slug])

const containerDialogItems = ref<any[]>([])
const isContainerDialogVisible = ref(false)

function openContainerDialog(item: any) {
	containerDialogItems.value = [{ id: item.id, code: item.code, name: item.name, agent_warehouse: item.agent_warehouse }]
	isContainerDialogVisible.value = true
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<Table :resource="data">
		<template #cell(code)="{ item }">
			<Link v-if="item.org_supplier_product_slug" :href="productRoute(item.org_supplier_product_slug)" class="primaryLink whitespace-nowrap">{{ item.code }}</Link>
			<span v-else class="whitespace-nowrap font-medium">{{ item.code }}</span>
			<span v-if="item.name" class="block text-xs text-gray-500">{{ item.name }}</span>
		</template>
		<template #cell(supplier_name)="{ item }">
			<Link v-if="item.org_supplier_slug" :href="supplierRoute(item.org_supplier_slug)" class="secondaryLink">{{ item.supplier_name }}</Link>
			<span v-else>{{ item.supplier_name }}</span>
		</template>
		<template #cell(purchase_order_reference)="{ item }">
			<Link :href="purchaseOrderRoute(item.purchase_order_slug)" class="secondaryLink whitespace-nowrap">{{ item.purchase_order_reference }}</Link>
		</template>
		<template #cell(quantity)="{ item }">
			<span class="tabular-nums">{{ locale.number(item.quantity) }}</span>
		</template>
		<template #cell(notes)="{ item }">
			<div v-for="(entry, index) in item.notes" :key="index" class="text-sm">
				<span class="text-gray-700">{{ entry.note }}</span>
				<span class="block text-xs text-gray-500">{{ [entry.by, useFormatTime(entry.at)].filter(Boolean).join(" · ") }}</span>
			</div>
		</template>
		<template #cell(actions)="{ item }">
			<Button
				v-if="item.agent_warehouse?.containers_route"
				:label="ctrans('Add to shipment')"
				type="tertiary"
				size="xs"
				@click="openContainerDialog(item)"
			>
				<template #icon>
					<FontAwesomeIcon :icon="faTruckContainer" class="text-gray-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Table>

	<PurchaseOrderLineAddToContainerDialog v-if="containerDialogItems.length" v-model:visible="isContainerDialogVisible" :items="containerDialogItems" />
</template>
