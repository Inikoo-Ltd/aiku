<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-12 13:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faPaperclip } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"

library.add(faTimes, faCheck, faPaperclip)

export interface PaySupplierForm {
	store_route: routeType
	organisation_id: number
	purchase_order_id: number
	supplier_name: string | null
	types: { value: string; label: string }[]
	default_type: string
	default_amount: number
}

const props = defineProps<{
	payForm: PaySupplierForm
	currency: string
}>()

const visible = defineModel<boolean>("visible", { required: true })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const fullWidthInput = { pcInputText: { root: { class: "!w-full" } } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const emptyPayment = () => ({
	type: props.payForm.default_type,
	date: new Date() as Date | null,
	amount: props.payForm.default_amount as number | null,
	reference: "",
	notes: "",
	receipt: null as File | null,
})
const payment = ref(emptyPayment())
const saving = ref(false)
const errorMessage = ref<string | null>(null)

watch(visible, (isVisible) => {
	if (isVisible) {
		payment.value = emptyPayment()
		errorMessage.value = null
	}
})

const amountInputOptions = computed(() => locale.currencyInputOptions(props.currency))

const canSave = computed(() => !!payment.value.type && !!payment.value.date && !!payment.value.amount && !!payment.value.receipt)

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const save = () => {
	if (!canSave.value) return
	router.post(
		route(props.payForm.store_route.name, props.payForm.store_route.parameters),
		{
			organisation_id: props.payForm.organisation_id,
			purchase_order_id: props.payForm.purchase_order_id,
			type: payment.value.type,
			date: toIsoDate(payment.value.date as Date),
			amount: payment.value.amount,
			reference: payment.value.reference || null,
			notes: payment.value.notes || null,
			receipt: payment.value.receipt,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => (saving.value = true),
			onFinish: () => (saving.value = false),
			onSuccess: () => (visible.value = false),
			onError: (errors: Record<string, string>) => (errorMessage.value = Object.values(errors)[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal dismissableMask :header="ctrans('Pay supplier')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3">
			<p class="text-sm text-gray-600">
				<template v-if="payForm.supplier_name">{{ ctrans("Money you paid :supplier for this order, from the client's funds. It is recorded in your ledger and marks the deposit or balance on the order.", { supplier: payForm.supplier_name }) }}</template>
				<template v-else>{{ ctrans("Money you paid the supplier for this order, from the client's funds. It is recorded in your ledger and marks the deposit or balance on the order.") }}</template>
			</p>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Type") }} <span class="text-red-500">*</span></span>
				<Select v-model="payment.type" :options="payForm.types" optionLabel="label" optionValue="value" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="grid grid-cols-2 gap-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date") }} <span class="text-red-500">*</span></span>
					<DatePicker v-model="payment.date" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }} <span class="text-red-500">*</span></span>
					<InputNumber v-model="payment.amount" v-bind="amountInputOptions" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
			</div>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Bank reference (optional)") }}</span>
				<InputText v-model="payment.reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Notes (optional)") }}</span>
				<Textarea v-model="payment.notes" rows="2" autoResize maxlength="2000" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Receipt") }} <span class="text-red-500">*</span></span>
				<span class="ml-1 text-xs text-gray-400">({{ ctrans("receipt of your payment to the supplier") }})</span>
				<div class="mt-1">
					<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (payment.receipt = event.files[0] ?? null)">
						<template #chooseicon>
							<FontAwesomeIcon icon="fal fa-paperclip" class="text-gray-500" fixed-width aria-hidden="true" />
						</template>
					</FileUpload>
				</div>
			</div>
			<p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Record payment')" :disabled="!canSave" :loading="saving" @click="save">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
