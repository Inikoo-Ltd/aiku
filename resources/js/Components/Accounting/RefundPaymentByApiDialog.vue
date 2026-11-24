<!--
    * Author: Raul Perusquia <raul@inikoo.com>
    * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
    * Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import RadioButton from "primevue/radiobutton"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faUndoAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

export type ApiRefundOptions = {
	left_on_payment: number
	refund_invoices: { id: number; reference: string; left: number; max: number }[]
	credit: { balance: number; max: number } | null
	blocked: string | null
	route: routeType
}

const props = defineProps<{
	options: ApiRefundOptions
	currencyCode: string
	paymentReference: string
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currencyCode, amount)
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

type Source = { key: string; label: string; max: number; invoiceId: number | null }

const sources = computed<Source[]>(() => [
	...props.options.refund_invoices.map((refund) => ({
		key: `invoice-${refund.id}`,
		label: ctrans("Refund invoice :reference, :left left to pay", { reference: refund.reference, left: money(refund.left) }),
		max: refund.max,
		invoiceId: refund.id,
	})),
	...(props.options.credit
		? [{
			key: "credit",
			label: ctrans("Customer credit, balance :balance", { balance: money(props.options.credit.balance) }),
			max: props.options.credit.max,
			invoiceId: null,
		}]
		: []),
])

const sourceKey = ref<string | null>(null)
const amount = ref<number | null>(null)
const error = ref<string | null>(null)
const isSaving = ref(false)

const source = computed(() => sources.value.find((option) => option.key === sourceKey.value) ?? null)

watch(visible, (isVisible) => {
	if (!isVisible) {
		return
	}
	error.value = null
	sourceKey.value = sources.value.length === 1 ? sources.value[0].key : null
	amount.value = source.value?.max ?? null
}, { immediate: true })

watch(sourceKey, () => {
	error.value = null
	amount.value = source.value?.max ?? null
})

const effect = computed(() => {
	if (!source.value || !amount.value) {
		return null
	}
	if (source.value.invoiceId === null && props.options.credit) {
		return ctrans("Customer credit goes from :before to :after", {
			before: money(props.options.credit.balance),
			after: money(Math.round((props.options.credit.balance - amount.value) * 100) / 100),
		})
	}
	const refund = props.options.refund_invoices.find((invoice) => invoice.id === source.value?.invoiceId)
	return refund
		? ctrans("Refund invoice :reference gets :amount of the :left it owes", { reference: refund.reference, amount: money(amount.value), left: money(refund.left) })
		: null
})

const canSubmit = computed(() => !!source.value && !!amount.value && amount.value > 0 && amount.value <= source.value.max && !isSaving.value)

const submit = () => {
	if (!canSubmit.value || !source.value) {
		return
	}
	router.post(
		route(props.options.route.name, props.options.route.parameters),
		source.value.invoiceId ? { amount: amount.value, invoice_id: source.value.invoiceId } : { amount: amount.value, from_credit: true },
		{
			preserveScroll: true,
			onStart: () => {
				isSaving.value = true
				error.value = null
			},
			onSuccess: () => {
				visible.value = false
			},
			onError: (errors) => {
				error.value = Object.values(errors ?? {}).join(", ") || ctrans("The refund was not made")
			},
			onFinish: () => {
				isSaving.value = false
			},
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Refund by Checkout.com')" :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="flex flex-col gap-4 text-sm">
			<p class="text-gray-600">
				{{ ctrans("Sends money back to what the customer paid with in :reference. :left left to refund on this payment.", { reference: paymentReference, left: money(options.left_on_payment) }) }}
			</p>

			<p v-if="options.blocked" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
				{{ options.blocked }}
			</p>

			<template v-else>
				<div class="flex flex-col gap-2">
					<span class="font-medium">{{ ctrans("Take the money from") }} <span class="text-red-500">*</span></span>
					<label v-for="option in sources" :key="option.key" class="flex cursor-pointer items-center gap-2">
						<RadioButton v-model="sourceKey" :value="option.key" :inputId="option.key" />
						<span>{{ option.label }}</span>
					</label>
				</div>

				<div v-if="source" class="flex flex-col gap-1">
					<span class="font-medium">{{ ctrans("Amount") }} <span class="text-red-500">*</span></span>
					<InputNumber
						v-model="amount"
						v-bind="locale.currencyInputOptions(currencyCode)"
						:min="0"
						:max="source.max"
						:class="fieldFocusClass"
						fluid
					/>
					<span class="text-xs text-gray-500">{{ ctrans("At most :max", { max: money(source.max) }) }}</span>
				</div>

				<p v-if="effect" class="text-gray-600">{{ effect }}</p>
			</template>

			<p v-if="error" class="text-xs text-red-500">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button v-if="!options.blocked" type="primary" full :label="amount ? ctrans('Refund :amount', { amount: money(amount) }) : ctrans('Refund')" :loading="isSaving" :disabled="!canSubmit" @click="submit">
					<template #icon>
						<FontAwesomeIcon :icon="faUndoAlt" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
