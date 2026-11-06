<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faTags, faPen } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type PriceQuery = {
	state: "pending" | "accepted" | "rejected" | "adjusted"
	label: string
	unit_cost: number | string
	previous_unit_cost: number | string | null
	note: string | null
	asked_by: string | null
}

const props = defineProps<{
	item: {
		code: string
		unit_cost: number | string
		net_currency?: string
		price_query: PriceQuery | null
		askPriceRoute: routeType | null
		answerPriceRoute: routeType | null
	}
	unitsPerLevel: number
	costLabel: string
	updateSupplierCost?: boolean
}>()

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const money = (unitCost: number | string | null) => locale.currencyFormat(props.item.net_currency ?? "EUR", Number(unitCost ?? 0) * props.unitsPerLevel)

const query = computed(() => props.item.price_query)
const isPending = computed(() => query.value?.state === "pending")

const stateStyles: Record<PriceQuery["state"], { icon: typeof faTags; iconClass: string; boxClass: string }> = {
	pending: { icon: faTags, iconClass: "text-amber-600", boxClass: "border-amber-300 bg-amber-50 text-amber-800" },
	accepted: { icon: faCheck, iconClass: "text-green-600", boxClass: "border-green-200 bg-green-50 text-green-800" },
	rejected: { icon: faTimes, iconClass: "text-red-500", boxClass: "border-red-200 bg-red-50 text-red-800" },
	adjusted: { icon: faPen, iconClass: "text-sky-600", boxClass: "border-sky-200 bg-sky-50 text-sky-800" },
}

const stateStyle = computed(() => (query.value ? stateStyles[query.value.state] : null))

const isWaitingForAnswer = computed(() => isPending.value && !!props.item.answerPriceRoute)

const dialog = ref<"ask" | "adjust" | null>(null)
const answeringWith = ref<"accepted" | "rejected" | "adjusted" | null>(null)

const form = useForm<{ price: number | null; note: string | null }>({
	price: null,
	note: null,
})

const formError = computed(() => Object.values(form.errors)[0] as string | undefined)

function openDialog(kind: "ask" | "adjust") {
	form.reset()
	form.clearErrors()
	const startCost = kind === "ask" && isPending.value ? query.value?.unit_cost : props.item.unit_cost
	form.price = Math.round(Number(startCost ?? 0) * props.unitsPerLevel * 10000) / 10000
	form.note = kind === "ask" && isPending.value ? query.value?.note ?? null : null
	dialog.value = kind
}

const unitCost = () => Number(((form.price ?? 0) / (props.unitsPerLevel || 1)).toFixed(6))

function ask() {
	const askRoute = props.item.askPriceRoute
	if (!askRoute) {
		return
	}

	form
		.transform(() => ({ unit_cost: unitCost(), note: form.note || null }))
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
					{{ query.label }}
				</div>
				<div class="tabular-nums">
					{{ ctrans("The supplier asks :new, was :old", { new: money(query.unit_cost), old: money(query.previous_unit_cost) }) }}
					<span class="text-gray-500">({{ costLabel }})</span>
				</div>
				<div v-if="query.state === 'adjusted'" class="font-semibold tabular-nums">
					{{ ctrans("New price: :price", { price: money(item.unit_cost) }) }}
					<span class="font-normal text-gray-500">({{ costLabel }})</span>
				</div>
				<div v-if="query.note" class="italic opacity-60">{{ query.note }}</div>
				<div v-if="query.asked_by" class="text-gray-500">{{ ctrans("Asked by :name", { name: query.asked_by }) }}</div>
			</div>

			<div v-if="item.answerPriceRoute" class="flex flex-wrap items-center gap-1.5">
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Accept')"
					:loading="answeringWith === 'accepted'"
					:disabled="!!answeringWith && answeringWith !== 'accepted'"
					@click="answer('accepted')"
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
				:label="isPending ? ctrans('Change the asked price') : ctrans('Supplier changed the price')"
				:tooltip="ctrans('Ask the organisation to accept, reject or adjust a new price from the supplier, until production starts')"
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
			:header="dialog === 'ask' ? ctrans('New supplier price for :code', { code: item.code }) : ctrans('Your price for :code', { code: item.code })"
			:style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible) => !visible && (dialog = null)"
		>
			<div class="space-y-3 text-sm">
				<p class="text-gray-600">
					{{ ctrans("Current price: :price", { price: money(item.unit_cost) }) }} <span class="text-gray-500">({{ costLabel }})</span>
				</p>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ dialog === "ask" ? ctrans("Price the supplier asks") : ctrans("Price for this line") }} ({{ costLabel }}<template v-if="item.net_currency">, {{ item.net_currency }}</template>)</span>
					<InputNumber v-model="form.price" :min="0" :maxFractionDigits="4" :class="fieldFocusClass" fluid />
				</label>
				<label v-if="dialog === 'ask'" class="block space-y-1">
					<span class="text-xs text-gray-500">{{ ctrans("Note for the organisation (optional)") }}</span>
					<Textarea v-model="form.note" rows="2" autoResize fluid :class="fieldFocusClass" />
				</label>
				<p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="dialog = null">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button
					type="tertiary"
					:label="dialog === 'ask' ? ctrans('Ask the organisation') : ctrans('Save price')"
					:loading="form.processing"
					:disabled="!form.price"
					@click="dialog === 'ask' ? ask() : answer('adjusted')"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</Dialog>
	</div>
</template>
