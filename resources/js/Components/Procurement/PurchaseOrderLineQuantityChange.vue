<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 23 Oct 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useForm } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faBoxes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { getOrderingLevels, unitsPerOrderingLevel, type OrderingLevel } from "@/Composables/useOrderingLevel"
import { useLocaleStore } from "@/Stores/locale"
import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"

type QuantityQuery = {
	state: "pending" | "accepted" | "rejected"
	label: string
	quantity: number | string
	previous_quantity: number | string | null
	note: string | null
	asked_by: string | null
}

const props = defineProps<{
	item: {
		code: string
		quantity_ordered: number | string
		quantity_query: QuantityQuery | null
		askQuantityRoute: routeType | null
		answerQuantityRoute: routeType | null
		units_per_carton?: number | string | null
		units_per_pack?: number | string | null
	}
	level: OrderingLevel
}>()

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const levels = getOrderingLevels()
const levelOptions = levels.map((level) => ({ label: level.quantity, value: level.key, icon: level.icon }))
const quantityLabelOf = (level: OrderingLevel) => levels.find((option) => option.key === level)?.quantity ?? ""

const atLevel = (units: number | string | null, level: OrderingLevel = props.level) =>
	Math.round((Number(units ?? 0) / unitsPerOrderingLevel(props.item, level)) * 1000) / 1000
const quantity = (units: number | string | null, level: OrderingLevel = props.level) => `${locale.number(atLevel(units, level))} ${quantityLabelOf(level)}`

const query = computed(() => props.item.quantity_query)
const isPending = computed(() => query.value?.state === "pending")

const stateStyles: Record<QuantityQuery["state"], { icon: typeof faBoxes; iconClass: string; boxClass: string }> = {
	pending: { icon: faBoxes, iconClass: "text-amber-600", boxClass: "border-amber-300 bg-amber-50 text-amber-800" },
	accepted: { icon: faCheck, iconClass: "text-green-600", boxClass: "border-green-200 bg-green-50 text-green-800" },
	rejected: { icon: faTimes, iconClass: "text-red-500", boxClass: "border-red-200 bg-red-50 text-red-800" },
}

const stateStyle = computed(() => (query.value ? stateStyles[query.value.state] : null))

const isDialogOpen = ref(false)
const dialogLevel = ref<OrderingLevel>("cartons")
const answeringWith = ref<"accepted" | "rejected" | null>(null)

const form = useForm<{ quantity: number | null; note: string | null }>({
	quantity: null,
	note: null,
})

const onError = (errors: Record<string, string>) =>
	notify({ title: ctrans("Something went wrong"), text: Object.values(errors ?? {})[0], type: "error" })

const startUnits = () => Number(isPending.value ? query.value?.quantity : props.item.quantity_ordered)

function openDialog() {
	form.reset()
	form.clearErrors()
	dialogLevel.value = levels.some((level) => level.key === props.level) ? props.level : "cartons"
	form.quantity = atLevel(startUnits(), dialogLevel.value)
	form.note = isPending.value ? query.value?.note ?? null : null
	isDialogOpen.value = true
}

const unitsAt = (quantityAtLevel: number | null, level: OrderingLevel) =>
	Number(((quantityAtLevel ?? 0) * unitsPerOrderingLevel(props.item, level)).toFixed(3))

watch(dialogLevel, (newLevel, oldLevel) => {
	if (!isDialogOpen.value || form.quantity === null) {
		return
	}

	const units = form.quantity === atLevel(startUnits(), oldLevel) ? startUnits() : unitsAt(form.quantity, oldLevel)
	form.quantity = atLevel(units, newLevel)
})

function unitsToAsk() {
	if (form.quantity === atLevel(startUnits(), dialogLevel.value)) {
		return startUnits()
	}

	return unitsAt(form.quantity, dialogLevel.value)
}

function ask() {
	const askRoute = props.item.askQuantityRoute
	if (!askRoute) {
		return
	}

	form
		.transform(() => ({ quantity_ordered: unitsToAsk(), note: form.note || null }))
		.post(route(askRoute.name, askRoute.parameters), {
			preserveScroll: true,
			onSuccess: () => (isDialogOpen.value = false),
			onError,
		})
}

function answer(answerValue: "accepted" | "rejected") {
	const answerRoute = props.item.answerQuantityRoute
	if (!answerRoute) {
		return
	}

	answeringWith.value = answerValue
	form
		.transform(() => ({ answer: answerValue, quantity_ordered: query.value?.quantity }))
		.patch(route(answerRoute.name, answerRoute.parameters), {
			preserveScroll: true,
			onError,
			onFinish: () => (answeringWith.value = null),
		})
}
</script>

<template>
	<div>
		<div v-if="query || item.askQuantityRoute" class="space-y-1 pt-1 text-left text-xs">
			<div v-if="query && stateStyle" class="relative space-y-0.5 rounded border px-2 py-1" :class="stateStyle.boxClass">
				<PingIcon
					v-if="isPending && item.answerQuantityRoute"
					v-tooltip="ctrans('Waiting for your answer')"
					class="!absolute -right-1 -top-1 text-[7px] text-amber-500"
					aria-hidden="true"
				/>
				<div class="flex items-center gap-1.5 font-medium">
					<FontAwesomeIcon :icon="stateStyle.icon" :class="stateStyle.iconClass" fixed-width aria-hidden="true" />
					{{ query.label }}
				</div>
				<div class="tabular-nums">
					{{ ctrans("The agent asks for :new, was :old", { new: quantity(query.quantity), old: quantity(query.previous_quantity) }) }}
				</div>
				<div v-if="query.note" class="italic opacity-60">{{ query.note }}</div>
				<div v-if="query.asked_by" class="text-gray-500">{{ ctrans("Asked by :name", { name: query.asked_by }) }}</div>
			</div>

			<div v-if="item.answerQuantityRoute" class="flex flex-wrap items-center gap-1.5">
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
					:label="ctrans('Keep old quantity')"
					:loading="answeringWith === 'rejected'"
					:disabled="!!answeringWith && answeringWith !== 'rejected'"
					@click="answer('rejected')"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>

			<Button
				v-if="item.askQuantityRoute"
				type="tertiary"
				size="xs"
				:label="isPending ? ctrans('Change the asked quantity') : ctrans('Ask for another quantity')"
				:tooltip="ctrans('Ask the organisation to accept or reject a new quantity for this line, possible until it is shipped')"
				@click="openDialog"
			>
				<template #icon>
					<FontAwesomeIcon :icon="faBoxes" class="text-amber-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>

		<Dialog
			:visible="isDialogOpen"
			modal
			:header="ctrans('New quantity for :code', { code: item.code })"
			:style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible) => !visible && (isDialogOpen = false)"
		>
			<div class="space-y-3 text-sm">
				<SegmentedToggle v-model="dialogLevel" :options="levelOptions" :ariaLabel="ctrans('Ask in cartons, SKOs or units')" />
				<p class="text-gray-600">{{ ctrans("Ordered now: :quantity", { quantity: quantity(item.quantity_ordered, dialogLevel) }) }}</p>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ ctrans("Quantity to order") }} ({{ quantityLabelOf(dialogLevel) }})</span>
					<InputNumber v-model="form.quantity" :min="0" :maxFractionDigits="3" :class="fieldFocusClass" fluid />
				</label>
				<label class="block space-y-1">
					<span class="text-xs text-gray-500">{{ ctrans("Note for the organisation (optional)") }}</span>
					<Textarea v-model="form.note" rows="2" autoResize fluid :class="fieldFocusClass" />
				</label>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isDialogOpen = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="tertiary" :label="ctrans('Ask the organisation')" :loading="form.processing" :disabled="!form.quantity" @click="ask">
					<template #icon>
						<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</template>
		</Dialog>
	</div>
</template>
