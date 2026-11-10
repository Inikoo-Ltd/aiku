<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-10 14:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import RadioButton from "primevue/radiobutton"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExternalLink, faCoins } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Stock = {
	id: number
	code: string
	name?: string | null
	quantity: number
	purchase_order_reference?: string | null
	purchase_order_state?: string | null
}

type PurchaseOrderLine = {
	id: number
	purchase_order_slug: string
	purchase_order_reference: string
	purchase_order_state: string
	is_current: boolean
	quantity_ordered: number
	quantity_at_agent: number
	has_goods_receipt: boolean
	deposit_amount: number | null
	deposit_paid_at: string | null
	currency_code: string | null
}

const props = defineProps<{
	stock: Stock
	organisation: string
}>()

const emit = defineEmits<{
	(e: "linked"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const lines = ref<PurchaseOrderLine[]>([])
const selectedLineId = ref<number | null>(null)
const isLoading = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

const purchaseOrderUrl = (line: PurchaseOrderLine) => route("grp.org.agent.purchase_orders.show", [props.organisation, line.purchase_order_slug])
const hasDeposit = (line: PurchaseOrderLine) => !!line.deposit_paid_at || (line.deposit_amount ?? 0) > 0
const depositLabel = (line: PurchaseOrderLine) =>
	line.deposit_amount && line.currency_code
		? ctrans("Deposit :amount", { amount: locale.currencyFormat(line.currency_code, line.deposit_amount) })
		: ctrans("Deposit paid")

const canSubmit = computed(() => selectedLineId.value !== null && !isSaving.value)

async function fetchLines() {
	isLoading.value = true
	error.value = null
	lines.value = []
	selectedLineId.value = null
	try {
		const response = await axios.get(route("grp.org.agent.warehouse.purchase_order_lines", [props.organisation, props.stock.id]))
		lines.value = response.data ?? []
		const linkableLines = lines.value.filter((line) => !line.has_goods_receipt)
		selectedLineId.value = (linkableLines.find((line) => line.is_current) ?? (linkableLines.length === 1 ? linkableLines[0] : null))?.id ?? null
	} catch (exception: any) {
		error.value = exception?.response?.data?.message ?? ctrans("Something went wrong")
	} finally {
		isLoading.value = false
	}
}

watch(
	[visible, () => props.stock.id],
	([isVisible]) => {
		if (isVisible) {
			fetchLines()
		}
	},
	{ immediate: true }
)

function submit() {
	if (!canSubmit.value) {
		return
	}

	router.patch(
		route("grp.org.agent.warehouse.link_purchase_order", [props.organisation, props.stock.id]),
		{ purchase_order_transaction_id: selectedLineId.value },
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				error.value = null
			},
			onFinish: () => (isSaving.value = false),
			onSuccess: () => {
				visible.value = false
				emit("linked")
			},
			onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog
		v-model:visible="visible"
		modal
		:header="ctrans('Link :code to its purchase order', { code: stock.code })"
		:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
		:draggable="false"
	>
		<div class="space-y-4 text-sm">
			<div class="text-gray-700">
				<span class="whitespace-nowrap font-medium text-gray-800">{{ stock.code }}</span>
				<span v-if="stock.name" class="ml-1 text-xs text-gray-500">{{ stock.name }}</span>
				<p class="mt-1 text-xs text-gray-500">
					{{ ctrans(":units units in the warehouse", { units: locale.number(stock.quantity) }) }}
					<template v-if="stock.purchase_order_reference">
						· {{ ctrans("linked to :order, but not to its line", { order: stock.purchase_order_reference }) }}
					</template>
				</p>
			</div>

			<div v-if="isLoading" class="flex justify-center py-6">
				<LoadingIcon />
			</div>

			<p v-else-if="!lines.length && !error" class="text-gray-500">
				{{ ctrans("No submitted or confirmed purchase order of this company has :code", { code: stock.code }) }}
			</p>

			<div v-else class="max-h-[50vh] space-y-2 overflow-y-auto">
				<label
					v-for="line in lines"
					:key="line.id"
					:for="`purchase-order-line-${line.id}`"
					class="flex items-start gap-3 rounded-md border p-3"
					:class="[
						line.has_goods_receipt ? 'cursor-not-allowed border-gray-200 bg-gray-50 opacity-60' : 'cursor-pointer border-gray-200 hover:border-[var(--app-accent-muted)]',
						selectedLineId === line.id ? '!border-[var(--app-accent)] bg-[var(--app-accent-soft)]' : '',
					]"
				>
					<RadioButton v-model="selectedLineId" :inputId="`purchase-order-line-${line.id}`" :value="line.id" :disabled="line.has_goods_receipt" class="mt-0.5" />
					<div class="min-w-0 flex-1">
						<div class="flex flex-wrap items-center gap-x-2">
							<a
								:href="purchaseOrderUrl(line)"
								target="_blank"
								rel="noopener"
								v-tooltip="ctrans('Open this purchase order in a new tab')"
								class="secondaryLink inline-flex items-center gap-1 whitespace-nowrap font-medium hover:underline"
								@click.stop
							>
								{{ line.purchase_order_reference }}
								<FontAwesomeIcon :icon="faExternalLink" class="text-xs" fixed-width aria-hidden="true" />
							</a>
							<span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ line.purchase_order_state }}</span>
							<span
								v-if="hasDeposit(line)"
								v-tooltip="line.deposit_paid_at ? ctrans('Deposit paid on :date', { date: new Date(line.deposit_paid_at).toLocaleDateString() }) : ctrans('Deposit paid')"
								class="inline-flex items-center gap-1 rounded border border-green-300 bg-green-50 px-1.5 py-0.5 text-xs text-green-700"
							>
								<FontAwesomeIcon :icon="faCoins" class="text-green-600" fixed-width aria-hidden="true" />
								{{ depositLabel(line) }}
							</span>
						</div>
						<p class="mt-1 text-xs text-gray-500">
							{{ ctrans(":units units ordered", { units: locale.number(line.quantity_ordered) }) }}
							<template v-if="line.quantity_at_agent > 0">
								· {{ ctrans(":units already in the warehouse", { units: locale.number(line.quantity_at_agent) }) }}
							</template>
						</p>
						<p v-if="line.has_goods_receipt" class="mt-1 text-xs text-gray-500">
							{{ ctrans("Already received through a goods receipt") }}
						</p>
					</div>
				</label>
			</div>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Link to this purchase order')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
