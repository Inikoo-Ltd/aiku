<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import ProductDialogHeader from "@/Components/Product/ProductDialogHeader.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faTags, faPen } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

type PriceQuery = {
	state: "pending" | "accepted" | "rejected" | "adjusted"
	label: string
	unit_cost: number | string
	previous_unit_cost: number | string | null
	note: string | null
	asked_by: string | null
	asked_at: string | null
	applied_at_once?: boolean
}

type PriceQuerySibling = {
	id: number
	purchase_order: string
	purchase_order_url: string
	net_currency: string | null
	unit_cost: number | string | null
	new_unit_cost: number | string
	previous_unit_cost: number | string | null
	note: string | null
	asked_by: string | null
}

const props = defineProps<{
	item: {
		code: string
		name?: string | null
		image_thumbnail?: object | null
		image_preview?: object | null
		unit_cost: number | string
		net_currency?: string
		supplier_unit_cost?: number | string | null
		supplier_unit_cost_currency?: string | null
		price_query: PriceQuery | null
		askPriceRoute: routeType | null
		price_change_applies_at_once?: boolean
		can_update_supplier_cost?: boolean
		answerPriceRoute: routeType | null
		price_query_siblings?: PriceQuerySibling[]
	}
	unitsPerLevel: number
	costLabel: string
	updateSupplierCost?: boolean
}>()

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const money = (unitCost: number | string | null, currency?: string | null) =>
	locale.currencyFormatUnitCost(currency ?? props.item.net_currency ?? "EUR", Number(unitCost ?? 0) * props.unitsPerLevel)

const siblings = computed(() => props.item.price_query_siblings ?? [])
const alsoAcceptIds = ref<number[]>([])

const query = computed(() => props.item.price_query)
const isPending = computed(() => query.value?.state === "pending")
const appliesAtOnce = computed(() => !!props.item.price_change_applies_at_once)
const isDirectChange = computed(() => !!query.value?.applied_at_once)
const alsoUpdateSupplierCost = ref(false)

const stateStyles: Record<PriceQuery["state"], { icon: typeof faTags; iconClass: string; boxClass: string }> = {
	pending: { icon: faTags, iconClass: "text-amber-600", boxClass: "border-amber-300 bg-amber-50 text-amber-800" },
	accepted: { icon: faCheck, iconClass: "text-green-600", boxClass: "border-green-200 bg-green-50 text-green-800" },
	rejected: { icon: faTimes, iconClass: "text-red-500", boxClass: "border-red-200 bg-red-50 text-red-800" },
	adjusted: { icon: faPen, iconClass: "text-sky-600", boxClass: "border-sky-200 bg-sky-50 text-sky-800" },
}

const stateStyle = computed(() => (query.value ? stateStyles[query.value.state] : null))

const confirmLabel = computed(() => {
	if (dialog.value === "ask") {
		return appliesAtOnce.value ? ctrans("Change price") : ctrans("Ask the organisation")
	}
	if (dialog.value === "accept") {
		return alsoAcceptIds.value.length
			? ctrans("Accept on :count orders", { count: alsoAcceptIds.value.length + 1 })
			: ctrans("Accept on this order only")
	}

	return ctrans("Save price")
})

function confirmDialog() {
	if (dialog.value === "ask") {
		ask()
	} else if (dialog.value === "accept") {
		answer("accepted")
	} else {
		answer("adjusted")
	}
}

const dialogHeader = computed(() => {
	if (dialog.value === "ask") {
		return appliesAtOnce.value
			? ctrans("New price for :code", { code: props.item.code })
			: ctrans("New supplier price for :code", { code: props.item.code })
	}
	if (dialog.value === "accept") {
		return ctrans("Accept the new price for :code", { code: props.item.code })
	}

	return ctrans("Your price for :code", { code: props.item.code })
})

const isWaitingForAnswer = computed(() => isPending.value && !!props.item.answerPriceRoute)

const dialog = ref<"ask" | "adjust" | "accept" | null>(null)
const answeringWith = ref<"accepted" | "rejected" | "adjusted" | null>(null)

const form = useForm<{ price: number | null; note: string | null }>({
	price: null,
	note: null,
})

const formError = computed(() => Object.values(form.errors)[0] as string | undefined)

const currentSupplierUnitCost = computed(() => {
	const supplierUnitCost = props.item.supplier_unit_cost
	const currency = props.item.supplier_unit_cost_currency
	const isSameCurrency = !currency || currency === props.item.net_currency

	if (supplierUnitCost === undefined || supplierUnitCost === null || !isSameCurrency || Number(supplierUnitCost) === Number(props.item.unit_cost)) {
		return null
	}

	return supplierUnitCost
})

function askStartCost() {
	if (isPending.value) {
		return query.value?.unit_cost
	}

	return currentSupplierUnitCost.value ?? props.item.unit_cost
}

function onAccept() {
	if (!siblings.value.length) {
		answer("accepted")
		return
	}

	form.clearErrors()
	alsoAcceptIds.value = siblings.value.map((sibling) => sibling.id)
	dialog.value = "accept"
}

function openDialog(kind: "ask" | "adjust") {
	form.reset()
	form.clearErrors()
	const startCost = kind === "ask" ? askStartCost() : props.item.unit_cost
	form.price = Math.round(Number(startCost ?? 0) * props.unitsPerLevel * 10000) / 10000
	form.note = kind === "ask" && isPending.value ? query.value?.note ?? null : null
	alsoUpdateSupplierCost.value = false
	dialog.value = kind
}

const unitCost = () => Number(((form.price ?? 0) / (props.unitsPerLevel || 1)).toFixed(6))

function ask() {
	const askRoute = props.item.askPriceRoute
	if (!askRoute) {
		return
	}

	form
		.transform(() => ({
			unit_cost: unitCost(),
			note: form.note || null,
			...(appliesAtOnce.value && props.item.can_update_supplier_cost ? { update_supplier_cost: alsoUpdateSupplierCost.value } : {}),
		}))
		.post(route(askRoute.name, askRoute.parameters), {
			preserveScroll: true,
			onSuccess: () => (dialog.value = null),
		})
}

function answer(answerValue: "accepted" | "rejected" | "adjusted") {
	const answerRoute = props.item.answerPriceRoute
	if (!answerRoute) {
		return
	}

	answeringWith.value = answerValue
	form
		.transform(() => ({
			answer: answerValue,
			update_supplier_cost: answerValue !== "rejected" && !!props.updateSupplierCost,
			...(answerValue === "adjusted" ? { unit_cost: unitCost() } : {}),
			...(answerValue === "accepted" && dialog.value === "accept" ? { also_accept_transaction_ids: alsoAcceptIds.value } : {}),
		}))
		.patch(route(answerRoute.name, answerRoute.parameters), {
			preserveScroll: true,
			onSuccess: () => (dialog.value = null),
			onFinish: () => (answeringWith.value = null),
		})
}
</script>

<template>
	<div>
		<div v-if="query || item.askPriceRoute" class="space-y-1 text-xs">
			<div v-if="query && stateStyle" class="relative space-y-0.5 rounded border px-2 py-1" :class="stateStyle.boxClass">
				<PingIcon
					v-if="isWaitingForAnswer"
					v-tooltip="ctrans('Waiting for your answer')"
					class="!absolute -right-1 -top-1 text-[7px] text-amber-500"
					aria-hidden="true"
				/>
				<div class="flex items-center gap-1.5 font-medium">
					<FontAwesomeIcon :icon="stateStyle.icon" :class="stateStyle.iconClass" fixed-width aria-hidden="true" />
					{{ isDirectChange ? ctrans("Price changed") : query.label }}
				</div>
				<div class="tabular-nums">
					{{ isDirectChange
						? ctrans("Now :new, was :old", { new: money(query.unit_cost), old: money(query.previous_unit_cost) })
						: ctrans("The supplier asks :new, was :old", { new: money(query.unit_cost), old: money(query.previous_unit_cost) }) }}
					<span class="text-gray-500">({{ costLabel }})</span>
				</div>
				<div v-if="query.state === 'adjusted'" class="font-semibold tabular-nums">
					{{ ctrans("New price: :price", { price: money(item.unit_cost) }) }}
					<span class="font-normal text-gray-500">({{ costLabel }})</span>
				</div>
				<div v-if="query.note" class="italic opacity-60">{{ query.note }}</div>
				<div v-if="query.asked_by || query.asked_at" class="text-gray-500">
					<span v-if="query.asked_by">{{ isDirectChange ? ctrans("Changed by :name", { name: query.asked_by }) : ctrans("Asked by :name", { name: query.asked_by }) }}</span>
					{{ " " }}
					<span v-if="query.asked_at" v-tooltip="useFormatTime(query.asked_at, { formatTime: 'hm' })">{{
						query.asked_by
							? ctrans("on :date", { date: useFormatTime(query.asked_at, { formatTime: "aiku" }) })
							: isDirectChange
								? ctrans("Changed on :date", { date: useFormatTime(query.asked_at, { formatTime: "aiku" }) })
								: ctrans("Asked on :date", { date: useFormatTime(query.asked_at, { formatTime: "aiku" }) })
					}}</span>
				</div>
			</div>

			<div v-if="item.answerPriceRoute" class="flex flex-wrap items-center gap-1.5">
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Accept')"
					:loading="answeringWith === 'accepted'"
					:disabled="!!answeringWith && answeringWith !== 'accepted'"
					@click="onAccept"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Keep old price')"
					:loading="answeringWith === 'rejected'"
					:disabled="!!answeringWith && answeringWith !== 'rejected'"
					@click="answer('rejected')"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" size="xs" :label="ctrans('Set another price')" :disabled="!!answeringWith" @click="openDialog('adjust')">
					<template #icon>
						<FontAwesomeIcon :icon="faPen" class="text-gray-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
			<p v-if="formError && !dialog" class="text-xs text-red-600">{{ formError }}</p>

			<Button
				v-if="item.askPriceRoute"
				type="tertiary"
				size="xs"
				:label="appliesAtOnce ? ctrans('Change price') : isPending ? ctrans('Change the asked price') : ctrans('Supplier changed the price')"
				:tooltip="appliesAtOnce
					? ctrans('You also edit purchasing for the buying organisation, so the new price applies at once, until the goods are shipped')
					: ctrans('Ask the organisation to accept, reject or adjust a new price from the supplier, until the goods are shipped')"
				@click="openDialog('ask')"
			>
				<template #icon>
					<FontAwesomeIcon :icon="faTags" class="text-amber-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>

		<Dialog
			:visible="!!dialog"
			modal
			:header="dialogHeader"
			:style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible) => !visible && (dialog = null)"
		>
			<div v-if="dialog === 'accept' && query" class="space-y-3 text-sm">
				<ProductDialogHeader :code="item.code" :name="item.name" :image="item.image_preview ?? item.image_thumbnail" />
				<div class="rounded border border-green-200 bg-green-50 px-3 py-2">
					<div class="text-xs font-medium text-gray-500">{{ ctrans("This order") }}</div>
					<div class="tabular-nums">
						{{ money(query.previous_unit_cost) }} → <span class="font-semibold">{{ money(query.unit_cost) }}</span>
						<span class="text-xs text-gray-500">({{ costLabel }})</span>
					</div>
					<div v-if="query.note" class="text-xs italic text-gray-600">{{ ctrans("Reason: :note", { note: query.note }) }}</div>
				</div>

				<div class="space-y-1.5">
					<p class="text-xs text-gray-600">{{ ctrans("The supplier also asks a new price for :code on other orders. Accept those too:", { code: item.code }) }}</p>
					<label
						v-for="sibling in siblings"
						:key="sibling.id"
						class="flex cursor-pointer items-start gap-2 rounded border px-3 py-2 hover:bg-gray-50"
						:class="alsoAcceptIds.includes(sibling.id) ? 'border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)]' : 'border-gray-200'"
					>
						<Checkbox v-model="alsoAcceptIds" :value="sibling.id" class="mt-0.5" />
						<div class="min-w-0 flex-1 space-y-0.5">
							<a :href="sibling.purchase_order_url" target="_blank" class="whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline" @click.stop>
								{{ sibling.purchase_order }}
							</a>
							<div class="tabular-nums">
								{{ money(sibling.unit_cost ?? sibling.previous_unit_cost, sibling.net_currency) }} →
								<span class="font-semibold">{{ money(sibling.new_unit_cost, sibling.net_currency) }}</span>
								<span class="text-xs text-gray-500">({{ costLabel }})</span>
							</div>
							<div class="text-xs italic text-gray-600">
								{{ sibling.note ? ctrans("Reason: :note", { note: sibling.note }) : ctrans("No reason given") }}
							</div>
							<div v-if="sibling.asked_by" class="text-xs text-gray-500">{{ ctrans("Asked by :name", { name: sibling.asked_by }) }}</div>
						</div>
					</label>
				</div>
				<p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
			</div>
			<div v-else class="space-y-3 text-sm">
				<ProductDialogHeader :code="item.code" :name="item.name" :image="item.image_preview ?? item.image_thumbnail" />
				<p class="text-gray-600">
					{{ ctrans("Current price: :price", { price: money(item.unit_cost) }) }} <span class="text-gray-500">({{ costLabel }})</span>
				</p>
				<p v-if="dialog === 'ask' && !isPending && currentSupplierUnitCost !== null" class="text-xs text-amber-700">
					{{ ctrans("Filled in with the supplier product's current price: :price", { price: money(currentSupplierUnitCost) }) }}
				</p>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ dialog === "ask" && !appliesAtOnce ? ctrans("Price the supplier asks") : ctrans("Price for this line") }} ({{ costLabel }}<template v-if="item.net_currency">, {{ item.net_currency }}</template>)</span>
					<InputNumber v-model="form.price" @input="(event: InputNumberInputEvent) => (form.price = typeof event.value === 'number' ? event.value : null)" :min="0" v-bind="locale.unitCostInputOptions(item.net_currency)" :class="fieldFocusClass" fluid />
				</label>
				<label v-if="dialog === 'ask' && appliesAtOnce && item.can_update_supplier_cost" class="flex cursor-pointer select-none items-center gap-2 text-xs text-gray-600">
					<Checkbox v-model="alsoUpdateSupplierCost" binary size="small" />
					{{ ctrans("Also update supplier price") }}
				</label>
				<label v-if="dialog === 'ask'" class="block space-y-1">
					<span class="text-xs text-gray-500">{{ appliesAtOnce ? ctrans("Note (optional)") : ctrans("Note for the organisation (optional)") }}</span>
					<Textarea v-model="form.note" rows="2" autoResize fluid :class="fieldFocusClass" />
				</label>
				<p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
			</div>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="dialog = null">
						<template #icon>
							<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
						</template>
					</Button>
					<Button
						type="primary"
						full
						:label="confirmLabel"
						:loading="form.processing"
						:disabled="dialog !== 'accept' && !form.price"
						@click="confirmDialog"
					>
						<template #icon>
							<FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
