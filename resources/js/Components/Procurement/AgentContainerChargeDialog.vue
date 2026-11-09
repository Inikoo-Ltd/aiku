<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 06 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputNumber from "primevue/inputnumber"
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

export interface ChargeForm {
	store_route: routeType
	organisation_id: number
	stock_delivery_id: number
	types: { value: string; label: string; needs_receipt: boolean }[]
}

const props = defineProps<{
	chargeForm: ChargeForm
	currency: string
	hasCharges: boolean
}>()

const visible = defineModel<boolean>("visible", { required: true })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const fullWidthInput = { pcInputText: { root: { class: "!w-full" } } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const emptyCharge = () => ({
	type: "container_supplies",
	date: new Date() as Date | null,
	amount: null as number | null,
	notes: "",
	receipt: null as File | null,
})
const charge = ref(emptyCharge())
const saving = ref(false)
const errorMessage = ref<string | null>(null)

watch(visible, (isVisible) => {
	if (isVisible) {
		charge.value = emptyCharge()
		errorMessage.value = null
	}
})

const amountInputOptions = computed(() => locale.currencyInputOptions(props.currency))
const amountPlaceholder = computed(() => {
	const options = amountInputOptions.value
	if (!("currency" in options)) {
		return "0"
	}

	return new Intl.NumberFormat(options.locale, {
		style: "currency",
		currency: options.currency,
		currencyDisplay: options.currencyDisplay,
		minimumFractionDigits: options.minFractionDigits,
		maximumFractionDigits: options.maxFractionDigits,
	}).format(0)
})

const selectedType = computed(() => props.chargeForm.types.find((type) => type.value === charge.value.type))

const canSave = computed(() => !!charge.value.type && !!charge.value.date && !!charge.value.amount && (!selectedType.value?.needs_receipt || !!charge.value.receipt))

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const save = () => {
	if (!canSave.value) return
	router.post(
		route(props.chargeForm.store_route.name, props.chargeForm.store_route.parameters),
		{
			organisation_id: props.chargeForm.organisation_id,
			stock_delivery_id: props.chargeForm.stock_delivery_id,
			type: charge.value.type,
			date: toIsoDate(charge.value.date as Date),
			amount: charge.value.amount,
			notes: charge.value.notes || null,
			receipt: charge.value.receipt,
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
	<Dialog v-model:visible="visible" modal dismissableMask :header="ctrans('Add charge')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3">
			<p class="text-sm text-gray-600">
				{{ ctrans("Money you paid for this container: it is recorded in your ledger and added to the invoice.") }}
				<span v-if="hasCharges">{{ ctrans("Adding a charge sends this container's charges back to the paying organisation for approval.") }}</span>
			</p>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Type") }}</span>
				<Select v-model="charge.type" :options="chargeForm.types" optionLabel="label" optionValue="value" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="grid grid-cols-2 gap-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Date") }}</span>
					<DatePicker v-model="charge.date" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }}</span>
					<InputNumber v-model="charge.amount" v-bind="amountInputOptions" :min="0" class="mt-1 w-full" :placeholder="amountPlaceholder" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
			</div>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Notes") }}</span>
				<Textarea v-model="charge.notes" rows="2" autoResize maxlength="2000" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="block text-sm">
				<span v-if="!selectedType?.needs_receipt" class="text-gray-600">{{ ctrans("Receipt (optional)") }}</span>
				<span v-else class="text-gray-600">{{ ctrans("Receipt") }} <span class="text-red-500">*</span></span>
				<div class="mt-1">
					<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (charge.receipt = event.files[0] ?? null)">
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
				<Button type="primary" full :label="ctrans('Add charge')" :disabled="!canSave" :loading="saving" @click="save">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
