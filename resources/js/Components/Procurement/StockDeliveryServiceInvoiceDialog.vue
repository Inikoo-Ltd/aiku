<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import ToggleSwitch from "primevue/toggleswitch"
import Textarea from "primevue/textarea"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

export interface ServiceInvoiceRow {
	id: number
	type: string
	type_label: string
	issuer: string
	reference: string | null
	date: string
	currency_id: number
	currency_code: string
	exchange: number
	total_amount: number
	org_total_amount: number
	paid_at: string | null
	notes: string | null
	attachments: { name: string; ulid: string }[]
	allocations: { stock_delivery_id: number; reference: string; amount: number; route: routeType }[]
	update_route: routeType
	paid_route: routeType
	delete_route: routeType
}

const props = defineProps<{
	invoice: ServiceInvoiceRow | null
	types: { label: string; value: string }[]
	currencies: { id: number; code: string }[]
	orgCurrencyId: number
	stockDeliveries: { id: number; reference: string }[]
	stockDeliveryId: number
	storeRoute: routeType
}>()

const visible = defineModel<boolean>("visible", { required: true })

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] [&_textarea:focus]:!border-[--app-accent]"
const fullWidthPt = { pcInputText: { root: { class: "!w-full" } } }

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toIsoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

interface FormState {
	type: string
	issuer: string
	reference: string
	date: Date | null
	currency_id: number
	exchange: number | null
	total_amount: number | null
	stock_delivery_ids: number[]
	split_by_value: boolean
	amounts: Record<number, number | null>
	is_paid: boolean
	paid_at: Date | null
	notes: string
	files: File[]
}

const form = ref<FormState>(blankForm())
const errors = ref<Record<string, string>>({})
const loading = ref(false)

function blankForm(): FormState {
	const invoice = props.invoice
	return {
		type: invoice?.type ?? "freight",
		issuer: invoice?.issuer ?? "",
		reference: invoice?.reference ?? "",
		date: invoice ? fromIsoDate(invoice.date) : new Date(),
		currency_id: invoice?.currency_id ?? props.orgCurrencyId,
		exchange: invoice && invoice.currency_id !== props.orgCurrencyId ? invoice.exchange : null,
		total_amount: invoice?.total_amount ?? null,
		stock_delivery_ids: invoice ? invoice.allocations.map(row => row.stock_delivery_id) : [props.stockDeliveryId],
		split_by_value: !invoice || invoice.allocations.length < 2,
		amounts: Object.fromEntries((invoice?.allocations ?? []).map(row => [row.stock_delivery_id, row.amount])),
		is_paid: Boolean(invoice?.paid_at),
		paid_at: invoice?.paid_at ? fromIsoDate(invoice.paid_at) : null,
		notes: invoice?.notes ?? "",
		files: [],
	}
}

watch(visible, (isVisible) => {
	if (isVisible) {
		form.value = blankForm()
		errors.value = {}
	}
})

const deliveryOptions = computed(() => {
	const known = new Map(props.stockDeliveries.map(row => [row.id, row.reference]))
	props.invoice?.allocations.forEach(row => known.set(row.stock_delivery_id, row.reference))
	return [...known].map(([id, reference]) => ({ id, reference }))
})
const referenceOf = (id: number) => deliveryOptions.value.find(row => row.id === id)?.reference ?? String(id)

const currencyCode = computed(() => props.currencies.find(row => row.id === form.value.currency_id)?.code ?? "")
const isForeignCurrency = computed(() => form.value.currency_id !== props.orgCurrencyId)
const isHandSplit = computed(() => form.value.stock_delivery_ids.length > 1 && !form.value.split_by_value)
const handSplitTotal = computed(() => form.value.stock_delivery_ids.reduce((sum, id) => sum + (form.value.amounts[id] ?? 0), 0))
const handSplitBalanced = computed(() => !isHandSplit.value || Math.abs(handSplitTotal.value - (form.value.total_amount ?? 0)) < 0.005)

const canSave = computed(() =>
	form.value.issuer.trim() && form.value.date && form.value.total_amount && form.value.stock_delivery_ids.length && handSplitBalanced.value && (!form.value.is_paid || form.value.paid_at)
)

const save = () => {
	const f = form.value
	const data: Record<string, unknown> = {
		type: f.type,
		issuer: f.issuer.trim(),
		reference: f.reference.trim() || null,
		date: toIsoDate(f.date as Date),
		currency_id: f.currency_id,
		exchange: isForeignCurrency.value ? f.exchange : null,
		total_amount: f.total_amount,
		paid_at: f.is_paid && f.paid_at ? toIsoDate(f.paid_at) : null,
		notes: f.notes.trim() || null,
		allocations: f.stock_delivery_ids.map(id => ({
			stock_delivery_id: id,
			...(isHandSplit.value ? { amount: f.amounts[id] ?? 0 } : {}),
		})),
		...(f.files.length ? { attachments: f.files } : {}),
	}

	const target = props.invoice?.update_route ?? props.storeRoute
	router.post(route(target.name, target.parameters), props.invoice ? { ...data, _method: "patch" } : data, {
		preserveScroll: true,
		forceFormData: f.files.length > 0,
		onStart: () => (loading.value = true),
		onFinish: () => (loading.value = false),
		onSuccess: () => {
			visible.value = false
			notify({ title: props.invoice ? ctrans("Service invoice updated") : ctrans("Service invoice added"), type: "success" })
		},
		onError: (formErrors) => {
			errors.value = formErrors
			notify({ title: ctrans("Something went wrong"), text: Object.values(formErrors)[0], type: "error" })
		},
	})
}
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="invoice ? ctrans('Edit service invoice') : ctrans('Add service invoice')" class="w-full max-w-2xl">
		<div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Type") }}</span>
				<Select v-model="form.type" :options="types" optionLabel="label" optionValue="value" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Issued by") }}</span>
				<InputText v-model="form.issuer" :placeholder="ctrans('Forwarder, customs broker, haulier…')" class="mt-1 w-full" :class="fieldFocusClass" :invalid="Boolean(errors.issuer)" />
			</label>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Invoice reference") }}</span>
				<InputText v-model="form.reference" class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Invoice date") }}</span>
				<DatePicker v-model="form.date" dateFormat="dd/mm/yy" :manualInput="false" showIcon iconDisplay="input" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthPt" />
			</label>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Currency") }}</span>
				<Select v-model="form.currency_id" :options="currencies" optionLabel="code" optionValue="id" filter class="mt-1 w-full" :class="fieldFocusClass" />
			</label>
			<label class="block">
				<span class="text-gray-600">{{ ctrans("Total") }}</span>
				<InputNumber v-model="form.total_amount" mode="currency" :currency="currencyCode || 'EUR'" :min="0" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthPt" :invalid="Boolean(errors.total_amount)" />
			</label>
			<label v-if="isForeignCurrency" class="block sm:col-span-2">
				<span class="text-gray-600">{{ ctrans("Exchange rate to the organisation currency") }}</span>
				<InputNumber v-model="form.exchange" :minFractionDigits="2" :maxFractionDigits="6" :min="0" :placeholder="ctrans('Leave empty to use today\'s rate')" class="mt-1 w-full" :class="fieldFocusClass" :pt="fullWidthPt" />
			</label>

			<div class="sm:col-span-2">
				<span class="text-gray-600">{{ ctrans("Containers") }}</span>
				<MultiSelect v-model="form.stock_delivery_ids" :options="deliveryOptions" optionLabel="reference" optionValue="id" filter display="chip" class="mt-1 w-full" :class="fieldFocusClass" :invalid="Boolean(errors.allocations)" />
				<div v-if="form.stock_delivery_ids.length > 1" class="mt-2 flex items-center gap-2">
					<ToggleSwitch v-model="form.split_by_value" inputId="service-invoice-split" />
					<label for="service-invoice-split" class="text-gray-600">{{ ctrans("Split by the value of each container's goods") }}</label>
				</div>
				<div v-if="isHandSplit" class="mt-2 space-y-1.5">
					<div v-for="id in form.stock_delivery_ids" :key="id" class="flex items-center justify-between gap-3">
						<span class="text-gray-700">{{ referenceOf(id) }}</span>
						<InputNumber v-model="form.amounts[id]" mode="currency" :currency="currencyCode || 'EUR'" :min="0" class="w-40" :class="fieldFocusClass" :pt="fullWidthPt" />
					</div>
					<p class="text-right text-xs" :class="handSplitBalanced ? 'text-green-700' : 'text-amber-700'">
						{{ ctrans("Allocated :allocated of :total", { allocated: locale.currencyFormat(currencyCode, handSplitTotal), total: locale.currencyFormat(currencyCode, form.total_amount ?? 0) }) }}
					</p>
				</div>
				<p v-if="errors.allocations" class="mt-1 text-xs text-red-600">{{ errors.allocations }}</p>
			</div>

			<div class="flex items-center gap-2">
				<ToggleSwitch v-model="form.is_paid" inputId="service-invoice-paid" @update:modelValue="(isPaid: boolean) => { if (isPaid && !form.paid_at) form.paid_at = new Date() }" />
				<label for="service-invoice-paid" class="text-gray-600">{{ ctrans("Paid") }}</label>
			</div>
			<DatePicker v-if="form.is_paid" v-model="form.paid_at" dateFormat="dd/mm/yy" :manualInput="false" showIcon iconDisplay="input" class="w-full" :class="fieldFocusClass" :pt="fullWidthPt" :aria-label="ctrans('Paid on')" />

			<label class="block sm:col-span-2">
				<span class="text-gray-600">{{ ctrans("Notes") }}</span>
				<Textarea v-model="form.notes" rows="2" autoResize class="mt-1 w-full" :class="fieldFocusClass" />
			</label>

			<div class="sm:col-span-2">
				<span class="text-gray-600">{{ ctrans("Invoice file") }}</span>
				<div class="mt-1 flex flex-wrap items-center gap-2">
					<FileUpload mode="basic" :auto="false" customUpload multiple accept=".pdf,image/*" :maxFileSize="50000000" :chooseLabel="ctrans('Choose file')" @select="(event: { files: File[] }) => (form.files = event.files)" />
					<span v-for="file in invoice?.attachments ?? []" :key="file.ulid" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ file.name }}</span>
				</div>
			</div>
		</div>

		<template #footer>
			<Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false" />
			<Button type="save" :label="ctrans('Save')" :disabled="!canSave" :loading="loading" @click="save" />
		</template>
	</Dialog>
</template>
