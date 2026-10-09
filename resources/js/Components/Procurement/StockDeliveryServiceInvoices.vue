<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StockDeliveryServiceInvoiceDialog, { type ServiceInvoiceRow } from "@/Components/Procurement/StockDeliveryServiceInvoiceDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faPencil, faTrashAlt, faPaperclip } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faPlus, faPencil, faTrashAlt, faPaperclip)

const props = defineProps<{
	data: {
		list: ServiceInvoiceRow[]
		can_edit: boolean
		is_costed: boolean
		types: { label: string; value: string }[]
		org_currency: string
		org_currency_id: number
		currencies: { id: number; code: string }[]
		stock_delivery_id: number
		stock_deliveries: { id: number; reference: string }[]
		store_route: routeType
		index_route: routeType
	}
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const isDialogOpen = ref(false)
const editing = ref<ServiceInvoiceRow | null>(null)

const canChange = () => props.data.can_edit && !props.data.is_costed

const openDialog = (row: ServiceInvoiceRow | null) => {
	editing.value = row
	isDialogOpen.value = true
}

const allocatedHere = (row: ServiceInvoiceRow) => row.allocations.find(allocation => allocation.stock_delivery_id === props.data.stock_delivery_id)?.amount ?? 0

const onError = (errors: Record<string, string>) =>
	notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0], type: "error" })

const togglePaid = (row: ServiceInvoiceRow) =>
	router.patch(route(row.paid_route.name, row.paid_route.parameters), { paid_at: row.paid_at ? null : new Date().toISOString().slice(0, 10) }, { preserveScroll: true, onError })

const confirmDelete = (row: ServiceInvoiceRow) =>
	confirm.require({
		message: ctrans("Remove the invoice :reference from :issuer? The costs it filled go back to pending on every container it covers.", { reference: row.reference ?? "", issuer: row.issuer }),
		header: ctrans("Remove service invoice"),
		acceptLabel: ctrans("Remove"),
		rejectLabel: ctrans("Cancel"),
		acceptClass: "p-button-danger",
		accept: () =>
			router.delete(route(row.delete_route.name, row.delete_route.parameters), {
				preserveScroll: true,
				onSuccess: () => notify({ title: ctrans("Service invoice removed"), type: "success" }),
				onError,
			}),
	})
</script>

<template>
	<div class="mt-3 space-y-2 border-t border-gray-200 pt-3 text-sm">
		<div class="flex items-center justify-between">
			<Link :href="route(data.index_route.name, data.index_route.parameters)" class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:underline">
				{{ ctrans("Service invoices") }}
			</Link>
			<Button v-if="canChange()" type="transparent" size="xs" icon="fal fa-plus" :label="ctrans('Add')" @click="openDialog(null)" />
		</div>

		<p v-if="!data.list.length" class="text-gray-500">{{ ctrans("No freight, customs or other local invoices yet.") }}</p>
		<ul v-else class="space-y-1.5">
			<li v-for="row in data.list" :key="row.id" class="flex items-start justify-between gap-3">
				<span class="min-w-0 text-gray-700">
					<span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ row.type_label }}</span>
					{{ row.issuer }}
					<span v-if="row.reference" class="text-gray-400"> · {{ row.reference }}</span>
					<span class="block text-xs text-gray-500">
						{{ useFormatTime(row.date) }}
						<template v-if="row.allocations.length > 1"> · {{ ctrans(":count containers, :total in all", { count: String(row.allocations.length), total: String(locale.currencyFormat(row.currency_code, row.total_amount)) }) }}</template>
						<a v-for="file in row.attachments" :key="file.ulid" :href="route('grp.media.download', { id: file.ulid })" class="ml-1 text-gray-500 hover:text-gray-700" :title="file.name" :aria-label="file.name">
							<FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
						</a>
					</span>
				</span>
				<span class="flex shrink-0 items-center gap-2">
					<span class="text-right tabular-nums text-gray-800">
						{{ locale.currencyFormat(row.currency_code, allocatedHere(row)) }}
						<button
							type="button"
							class="block w-full text-right text-xs"
							:class="row.paid_at ? 'text-green-700' : 'text-amber-700'"
							:disabled="!data.can_edit"
							:title="data.can_edit ? (row.paid_at ? ctrans('Mark as unpaid') : ctrans('Mark as paid')) : undefined"
							@click="togglePaid(row)"
						>
							{{ row.paid_at ? ctrans("Paid") : ctrans("Unpaid") }}
						</button>
					</span>
					<template v-if="canChange()">
						<button type="button" class="text-gray-400 hover:text-gray-700" :title="ctrans('Edit')" :aria-label="ctrans('Edit')" @click="openDialog(row)">
							<FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
						</button>
						<button type="button" class="text-gray-400 hover:text-red-600" :title="ctrans('Remove')" :aria-label="ctrans('Remove')" @click="confirmDelete(row)">
							<FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
						</button>
					</template>
				</span>
			</li>
		</ul>
		<p v-if="data.list.some(row => row.type === 'tax')" class="text-xs text-gray-400">{{ ctrans("Import VAT is recovered, it is not part of the landed cost.") }}</p>

		<StockDeliveryServiceInvoiceDialog
			v-model:visible="isDialogOpen"
			:invoice="editing"
			:types="data.types"
			:currencies="data.currencies"
			:orgCurrencyId="data.org_currency_id"
			:stockDeliveries="data.stock_deliveries"
			:stockDeliveryId="data.stock_delivery_id"
			:storeRoute="data.store_route"
		/>
	</div>
</template>
