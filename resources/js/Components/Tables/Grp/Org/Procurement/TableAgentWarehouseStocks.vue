<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDolly, faBoxCheck, faBoxes, faCheck, faTimes, faExclamationTriangle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDolly, faBoxCheck, faBoxes)

const props = defineProps<{
	data: {}
	tab?: string
	canPack?: boolean
}>()

const confirm = useConfirm()
const locale = useLocaleStore()
const organisation = route().params["organisation"]
const productRoute = (slug: string) => route("grp.org.agent.org_supplier_products.show", [organisation, slug])
const supplierRoute = (slug: string) => route("grp.org.agent.org_suppliers.show", [organisation, slug])
const purchaseOrderRoute = (slug: string) => route("grp.org.agent.purchase_orders.show", [organisation, slug])

const packingId = ref<number | null>(null)
const packErrors = ref<Record<number, string>>({})

function pack(stock: any) {
	router.patch(route("grp.org.agent.warehouse.pack", [organisation, stock.id]), {}, {
		preserveScroll: true,
		onStart: () => {
			packingId.value = stock.id
			delete packErrors.value[stock.id]
		},
		onFinish: () => (packingId.value = null),
		onError: (errors: Record<string, string>) => (packErrors.value[stock.id] = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
	})
}

const _table = ref<InstanceType<typeof Table> | null>(null)
const selectedRows = ref<Record<string, boolean>>({})
const isPackable = (stock: any) => !!props.canPack && stock.state === "received"
const tableRows = computed<any[]>(() => (props.data as any)?.data ?? [])
const hasPackableRows = computed(() => tableRows.value.some(isPackable))
const rowsSelectedForPacking = computed(() => tableRows.value.filter((stock) => selectedRows.value[stock.id] && isPackable(stock)))
const isPackingSelected = ref(false)
const packSelectedError = ref<string | null>(null)

function onSelectRow(rows: Record<string, boolean>) {
	selectedRows.value = { ...rows }
}

function packSelected() {
	const rows = rowsSelectedForPacking.value
	if (!rows.length) {
		return
	}

	router.patch(route("grp.org.agent.warehouse.pack_many", [organisation]), { stocks: rows.map((stock) => stock.id) }, {
		preserveScroll: true,
		onStart: () => {
			isPackingSelected.value = true
			packSelectedError.value = null
		},
		onFinish: () => (isPackingSelected.value = false),
		onSuccess: () => {
			_table.value?.clearSelection()
			selectedRows.value = {}
		},
		onError: (errors: Record<string, string>) => (packSelectedError.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
	})
}

function confirmPackSelected(event: MouseEvent) {
	const unchecked = rowsSelectedForPacking.value.filter((stock) => stock.is_unchecked)
	if (!unchecked.length) {
		packSelected()
		return
	}

	confirm.require({
		group: "pack-warehouse-stock",
		target: event.currentTarget as HTMLElement,
		message: ctrans(":unchecked of the :count selected lines are not checked yet. Once packed they can no longer be checked. Pack them anyway?", {
			unchecked: locale.number(unchecked.length),
			count: locale.number(rowsSelectedForPacking.value.length),
		}),
		acceptLabel: ctrans("Pack anyway"),
		rejectLabel: ctrans("Cancel"),
		accept: packSelected,
	})
}

function confirmPack(event: MouseEvent, stock: any) {
	if (!stock.is_unchecked) {
		pack(stock)
		return
	}

	confirm.require({
		group: "pack-warehouse-stock",
		target: event.currentTarget as HTMLElement,
		message: ctrans(":units units of :code are not checked yet. Once packed they can no longer be checked. Pack them anyway?", {
			units: locale.number(stock.quantity),
			code: stock.code,
		}),
		acceptLabel: ctrans("Pack anyway"),
		rejectLabel: ctrans("Cancel"),
		accept: () => pack(stock),
	})
}
</script>

<template>
	<div>
		<Table
			ref="_table"
			:resource="data"
			:name="tab"
			class="mt-5"
			:isCheckBox="hasPackableRows"
			checkboxKey="id"
			:disabledCheckbox="(stock: any) => !isPackable(stock)"
			@onSelectRow="onSelectRow"
		>
			<template #before-table>
				<div v-if="canPack" class="flex flex-wrap items-center justify-end gap-2 border-b border-gray-200 px-3 py-1.5 sm:px-4">
					<p v-if="packSelectedError" class="text-xs text-red-500">{{ packSelectedError }}</p>
					<span v-tooltip="rowsSelectedForPacking.length ? ctrans('Mark the selected lines as packed for the next container') : ctrans('Tick the received lines in the table to mark them as packed at once')">
						<Button
							:label="ctransChoice('Mark :count line packed|Mark :count lines packed', rowsSelectedForPacking.length, { count: locale.number(rowsSelectedForPacking.length) })"
							type="tertiary"
							size="xs"
							:loading="isPackingSelected"
							:disabled="isPackingSelected || !rowsSelectedForPacking.length"
							@click="confirmPackSelected"
						>
							<template #icon>
								<FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
							</template>
						</Button>
					</span>
				</div>
			</template>
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
				<Link v-if="stock.purchase_order_slug" :href="purchaseOrderRoute(stock.purchase_order_slug)" class="secondaryLink whitespace-nowrap">{{ stock.purchase_order_reference }}</Link>
			</template>
			<template #cell(quantity)="{ item: stock }"><span class="tabular-nums">{{ locale.number(stock.quantity) }}</span></template>
			<template #cell(actions)="{ item: stock }">
				<div v-if="isPackable(stock)">
					<Button
						:label="ctrans('Packed')"
						:tooltip="ctrans('Mark these :units units as packed for the next container', { units: locale.number(stock.quantity) })"
						type="tertiary"
						size="xs"
						:loading="packingId === stock.id"
						:disabled="packingId === stock.id"
						@click="confirmPack($event, stock)"
					>
						<template #icon>
							<FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<p v-if="packErrors[stock.id]" class="mt-1 text-xs text-red-500">{{ packErrors[stock.id] }}</p>
				</div>
			</template>
		</Table>

		<ConfirmPopup group="pack-warehouse-stock">
			<template #container="{ message, acceptCallback, rejectCallback }">
				<div class="w-72 p-3 text-sm">
					<div class="flex gap-2 text-gray-700">
						<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
						<p>{{ message.message }}</p>
					</div>
					<div class="mt-3 flex justify-end gap-2">
						<Button type="tertiary" size="xs" :label="message.rejectLabel" @click="rejectCallback">
							<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
						</Button>
						<Button type="tertiary" size="xs" :label="message.acceptLabel" @click="acceptCallback">
							<template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
				</div>
			</template>
		</ConfirmPopup>
	</div>
</template>
