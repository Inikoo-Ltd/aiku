<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import DatePicker from "primevue/datepicker"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoice, faCheck } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
library.add(faFileInvoice, faCheck)

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: { data: { id: number; is_dispatched: boolean; date: string }[] }
	defaults: { prefix: string; first_number: number; date: string }
	splitsNote: string | null
	storeRoute: { name: string; parameters: Record<string, string> }
}>()

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const prefix = ref(props.defaults.prefix)
const firstNumber = ref<number | null>(props.defaults.first_number)
const date = ref<Date>(fromIsoDate(props.defaults.date))
const numberPallets = ref<number | null>(null)

const recentFrom = toLocalIsoDate(new Date(Date.now() - 30 * 86400000))
const initialSelection = Object.fromEntries(props.data.data.filter((row) => !row.is_dispatched && row.date >= recentFrom).map((row) => [row.id, true]))
const selectedRows = ref<Record<string, boolean>>({ ...initialSelection })
const selectedIds = computed(() => Object.keys(selectedRows.value).filter((id) => selectedRows.value[id]).map(Number))

const isSaving = ref(false)
const errors = ref<Record<string, string>>({})
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

function submit() {
	router.post(
		route(props.storeRoute.name, props.storeRoute.parameters),
		{
			delivery_note_ids: selectedIds.value,
			prefix: prefix.value,
			first_number: firstNumber.value,
			date: toLocalIsoDate(date.value),
			number_pallets: numberPallets.value,
		},
		{
			onStart: () => {
				isSaving.value = true
				errors.value = {}
			},
			onError: (bag) => (errors.value = bag),
			onFinish: () => (isSaving.value = false),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="flex flex-wrap items-end gap-4 px-4 py-3">
		<label class="flex flex-col gap-1 text-xs text-gray-500">
			{{ ctrans("Number prefix") }}
			<InputText v-model="prefix" size="small" class="w-28" :class="fieldFocusClass" />
		</label>
		<label class="flex flex-col gap-1 text-xs text-gray-500">
			{{ ctrans("First invoice number") }}
			<InputNumber v-model="firstNumber" :useGrouping="false" :min="1" size="small" inputClass="w-24" :class="fieldFocusClass" />
		</label>
		<label class="flex flex-col gap-1 text-xs text-gray-500">
			{{ ctrans("Date") }}
			<DatePicker v-model="date" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" size="small" :class="fieldFocusClass" />
		</label>
		<label class="flex flex-col gap-1 text-xs text-gray-500">
			{{ ctrans("Pallets") }}
			<InputNumber v-model="numberPallets" :useGrouping="false" :min="0" size="small" inputClass="w-20" :class="fieldFocusClass" />
		</label>
		<div class="flex flex-col gap-1">
			<Button
				type="tertiary"
				:label="ctrans('Create customs invoices for :count delivery notes', { count: String(selectedIds.length) })"
				:loading="isSaving"
				:disabled="!selectedIds.length || !prefix || !firstNumber"
				@click="submit">
				<template #icon>
					<FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</div>
		<p v-if="splitsNote" class="w-full text-xs text-gray-500">{{ splitsNote }}</p>
		<p v-for="(message, field) in errors" :key="field" class="w-full text-xs text-red-500">{{ message }}</p>
	</div>

	<Table
		:resource="data"
		isCheckBox
		:selectedRow="initialSelection"
		@onSelectRow="(rows: Record<string, boolean>) => (selectedRows = { ...rows })">
		<template #cell(reference)="{ item }">
			<span class="whitespace-nowrap">{{ item.reference }}</span>
		</template>
		<template #cell(date)="{ item }">
			<span class="whitespace-nowrap">{{ item.date }}</span>
		</template>
	</Table>
</template>
