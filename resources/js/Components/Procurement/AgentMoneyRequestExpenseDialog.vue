<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-09 13:15
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
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faCheck, faPaperclip } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"

library.add(faTimes, faCheck, faPaperclip)

const props = defineProps<{
	storeRoute: routeType
	organisationId: number
	clientCode: string
	currency: string
}>()

const emit = defineEmits<{ saved: [] }>()

const visible = defineModel<boolean>("visible", { required: true })

const locale = useLocaleStore()

const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)] focus:!border-[var(--app-accent)]"
const fullWidthInput = { pcInputText: { root: { class: "!w-full" } } }
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const emptyExpense = () => ({
	notes: "",
	date: new Date() as Date | null,
	amount: null as number | null,
	reference: "",
	receipt: null as File | null,
})
const expense = ref(emptyExpense())
const saving = ref(false)
const errorMessage = ref<string | null>(null)

watch(visible, (isVisible) => {
	if (isVisible) {
		expense.value = emptyExpense()
		errorMessage.value = null
	}
})

const amountInputOptions = computed(() => locale.currencyInputOptions(props.currency))

const canSave = computed(() => !!expense.value.notes.trim() && !!expense.value.date && !!expense.value.amount)

const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const save = () => {
	if (!canSave.value) return
	router.post(
		route(props.storeRoute.name, props.storeRoute.parameters),
		{
			organisation_id: props.organisationId,
			notes: expense.value.notes.trim(),
			date: toIsoDate(expense.value.date as Date),
			amount: expense.value.amount,
			reference: expense.value.reference || null,
			receipt: expense.value.receipt,
		},
		{
			preserveScroll: true,
			forceFormData: true,
			onStart: () => (saving.value = true),
			onFinish: () => (saving.value = false),
			onSuccess: () => {
				visible.value = false
				emit("saved")
			},
			onError: (errors: Record<string, string>) => (errorMessage.value = Object.values(errors)[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Dialog v-model:visible="visible" modal dismissableMask :header="ctrans('Add other expense')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3">
			<p class="text-sm text-gray-600">
				{{ ctrans("Money you paid, or still have to pay, for :client that belongs to no order or shipment, such as a bank charge. It is recorded in the ledger as an other expense and added to this request.", { client: clientCode }) }}
			</p>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("What it is for") }} <span class="text-red-500">*</span></span>
				<InputText v-model="expense.notes" maxlength="2000" :placeholder="ctrans('e.g. Bank transfer charge')" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="grid grid-cols-2 gap-3">
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Paid on") }} <span class="text-red-500">*</span></span>
					<DatePicker v-model="expense.date" dateFormat="dd/mm/yy" :maxDate="new Date()" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
				<label class="block text-sm">
					<span class="text-gray-600">{{ ctrans("Amount") }} <span class="text-red-500">*</span></span>
					<InputNumber v-model="expense.amount" v-bind="amountInputOptions" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthInput" />
				</label>
			</div>
			<label class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Reference (optional)") }}</span>
				<InputText v-model="expense.reference" maxlength="255" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<div class="block text-sm">
				<span class="text-gray-600">{{ ctrans("Receipt (optional)") }}</span>
				<div class="mt-1">
					<FileUpload mode="basic" :auto="false" customUpload accept=".pdf,image/*" :maxFileSize="20000000" :chooseLabel="ctrans('Choose file')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (expense.receipt = event.files[0] ?? null)">
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
				<Button type="primary" full :label="ctrans('Add expense')" :disabled="!canSave" :loading="saving" @click="save">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
