<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faPlus, faTrashAlt } from "@fal"

library.add(faFileInvoice, faPlus, faTrashAlt)

interface Charge {
	description: string
	type: "freight" | "other"
	amount: number | null
}

const props = defineProps<{
	route: routeType
	currencyCode: string
	isAgent: boolean
	invoice: {
		source: "agent" | "actual" | "estimated"
		reference: string | null
		date: string
		goods: number
		charges_list: { description: string; type?: "freight" | "other"; amount: number }[]
	} | null
}>()

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const isOpen = ref(false)
const isSaving = ref(false)
const errors = ref<Record<string, string>>({})

const reference = ref("")
const date = ref<Date | null>(null)
const goodsAmount = ref<number | null>(null)
const charges = ref<Charge[]>([])

const chargeTypes = [
	{ label: ctrans("Freight"), value: "freight" },
	{ label: ctrans("Other"), value: "other" },
]

const isActual = computed(() => props.invoice?.source === "actual")

const toIsoDate = (value: Date) => `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`

const open = () => {
	const fromActual = isActual.value && props.invoice
	reference.value = fromActual ? props.invoice!.reference ?? "" : ""
	date.value = fromActual ? new Date(props.invoice!.date) : null
	goodsAmount.value = props.invoice ? props.invoice.goods : null
	charges.value = (props.invoice?.charges_list ?? []).map(charge => ({ description: charge.description, type: charge.type ?? "other", amount: charge.amount }))
	errors.value = {}
	isOpen.value = true
}

const total = computed(() => (goodsAmount.value ?? 0) + charges.value.reduce((sum, charge) => sum + (charge.amount ?? 0), 0))

const save = () => {
	router.post(
		route(props.route.name, props.route.parameters),
		{
			reference: reference.value,
			date: date.value ? toIsoDate(date.value) : null,
			goods_amount: goodsAmount.value,
			charges: charges.value,
		},
		{
			preserveScroll: true,
			onStart: () => { isSaving.value = true },
			onFinish: () => { isSaving.value = false },
			onSuccess: () => { isOpen.value = false },
			onError: (formErrors) => { errors.value = formErrors },
		}
	)
}
</script>

<template>
	<div>
		<Button
			type="tertiary"
			size="xs"
			:label="isActual ? ctrans('Edit invoice') : props.invoice?.source === 'estimated' ? ctrans('Enter real invoice') : ctrans('Enter invoice')"
			icon="fal fa-file-invoice"
			@click="open"
		/>

		<Dialog
			v-model:visible="isOpen"
			modal
			dismissableMask
			:header="isAgent ? ctrans('Agent invoice received') : ctrans('Supplier invoice received')"
			:style="{ width: '40rem' }"
			:breakpoints="{ '768px': '95vw' }"
		>
			<p v-if="props.invoice?.source === 'estimated'" class="mb-4 text-sm text-gray-500">
				{{ ctrans("These figures replace the estimate. The estimate is kept for comparison.") }}
			</p>

			<div class="grid grid-cols-2 gap-4">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Invoice number") }}</span>
					<InputText v-model="reference" class="mt-1 w-full" :class="fieldFocusClass" />
					<span v-if="errors.reference" class="text-xs text-red-600">{{ errors.reference }}</span>
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Invoice date") }}</span>
					<DatePicker v-model="date" dateFormat="dd/mm/yy" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
					<span v-if="errors.date" class="text-xs text-red-600">{{ errors.date }}</span>
				</label>
				<label class="col-span-2 block text-sm">
					<span class="text-gray-600">{{ ctrans("Goods") }} ({{ currencyCode }})</span>
					<InputNumber v-model="goodsAmount" mode="currency" :currency="currencyCode" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-full' } } }" />
					<span v-if="errors.goods_amount" class="text-xs text-red-600">{{ errors.goods_amount }}</span>
				</label>
			</div>

			<div class="mt-5 text-sm">
				<div class="mb-2 text-gray-600">{{ isAgent ? ctrans("Agent charges") : ctrans("Supplier charges") }}</div>
				<div v-for="(charge, index) in charges" :key="index" class="mb-2 flex items-center gap-2">
					<InputText v-model="charge.description" :placeholder="ctrans('Description')" size="small" class="flex-1" :class="fieldFocusClass" />
					<SegmentedToggle v-model="charge.type" :options="chargeTypes" />
					<InputNumber v-model="charge.amount" mode="currency" :currency="currencyCode" :min="0" size="small" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!w-32 !text-right' } } }" :aria-label="ctrans('Amount')" />
					<Button type="negative" size="xs" icon="fal fa-trash-alt" :aria-label="ctrans('Remove')" @click="charges.splice(index, 1)" />
				</div>
				<Button type="tertiary" size="xs" icon="fal fa-plus" :label="ctrans('Add charge')" @click="charges.push({ description: '', type: 'other', amount: null })" />
				<span v-if="Object.keys(errors).some(key => key.startsWith('charges'))" class="mt-1 block text-xs text-red-600">{{ ctrans("Every charge needs a description and an amount") }}</span>
			</div>

			<div class="mt-5 flex items-center justify-between border-t border-gray-200 pt-3 text-sm font-semibold text-gray-700">
				<span>{{ ctrans("Total") }}</span>
				<span>{{ locale.currencyFormat(currencyCode, total) }}</span>
			</div>
			<span v-if="errors.invoice" class="mt-2 block text-sm text-red-600">{{ errors.invoice }}</span>

			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isOpen = false" />
				<Button type="save" :label="ctrans('Save invoice')" :loading="isSaving" @click="save" />
			</template>
		</Dialog>
	</div>
</template>
