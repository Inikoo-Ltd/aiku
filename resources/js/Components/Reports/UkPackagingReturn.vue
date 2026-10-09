<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import DatePicker from "primevue/datepicker"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faArrowUp, faArrowDown, faFileCertificate, faPlus, faTrashAlt } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faDownload, faArrowUp, faArrowDown, faFileCertificate, faPlus, faTrashAlt)

type Cell = { kg: number; previous_kg: number | null; change: number | null; warning: boolean }

const props = defineProps<{
	data?: {
		from: string
		to: string
		submission_period: string | null
		has_data: boolean
		kg: number
		previous_kg: number | null
		materials: string[]
		form: ({ activity: string; label: string; cells: Record<string, Cell> } & Cell)[]
		lines: { activity: string; type: string; class: string; material: string; ram: string | null; kg: number }[]
		manual_lines: {
			id: number
			date_from: string
			date_to: string
			activity: string
			packaging_type: string
			packaging_class: string
			material_category: string
			from_nation: string | null
			to_nation: string | null
			kg: number
			notes: string | null
			user: string | null
		}[]
	}
	ownBrandImports: boolean
	downloadUrl: string
	organisationId: number
	materials: Record<string, string>
	period: { from: string; to: string }
}>()

const emit = defineEmits<{ (e: "update:ownBrandImports", value: boolean): void }>()

const locale = useLocaleStore()

const materialLabels: Record<string, string> = {
	PC: ctrans("Paper or card"),
	GL: ctrans("Glass"),
	PL: ctrans("Plastic"),
	AL: ctrans("Aluminium"),
	ST: ctrans("Steel"),
	WD: ctrans("Wood"),
	FC: ctrans("Fibre composite"),
	OT: ctrans("Other"),
}

const importMethod = computed({
	get: () => (props.ownBrandImports ? "all" : "once"),
	set: (value: string) => emit("update:ownBrandImports", value === "all"),
})
const importMethods = [
	{ label: ctrans("Own-brand imports reported once"), value: "once" },
	{ label: ctrans("Own-brand imports also under Imported"), value: "all" },
]

const presets = [
	{ label: ctrans("Import cartons"), activity: "IM", packaging_type: "NH", packaging_class: "P2", material_category: "paper_cardboard" },
	{ label: ctrans("Self-managed waste"), activity: "PF", packaging_type: "OW", packaging_class: "P1", material_category: "paper_cardboard" },
	{ label: ctrans("Other"), activity: "PF", packaging_type: "HH", packaging_class: "P1", material_category: "paper_cardboard" },
]
const codeOptions = (codes: string[]) => codes.map((code) => ({ label: code, value: code }))
const materialOptions = computed(() => Object.entries(props.materials).map(([value, label]) => ({ value, label })))
const nationOptions = [
	{ label: ctrans("England"), value: "EN" },
	{ label: ctrans("Northern Ireland"), value: "NI" },
	{ label: ctrans("Scotland"), value: "SC" },
	{ label: ctrans("Wales"), value: "WS" },
]

const toIso = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const fromIso = (iso: string) => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}

const dialogOpen = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const blankLine = () => ({
	...presets[0],
	label: undefined,
	date_from: fromIso(props.period.from),
	date_to: fromIso(props.period.to),
	from_nation: null as string | null,
	to_nation: null as string | null,
	kg: null as number | null,
	notes: "",
})
const line = ref(blankLine())
const openDialog = () => {
	line.value = blankLine()
	errors.value = {}
	dialogOpen.value = true
}
const applyPreset = (preset: (typeof presets)[number]) => {
	line.value = { ...line.value, activity: preset.activity, packaging_type: preset.packaging_type, packaging_class: preset.packaging_class, material_category: preset.material_category }
}
const saveLine = () => {
	saving.value = true
	const { label, ...data } = line.value
	router.post(route("grp.models.epr_manual_line.store", { organisation: props.organisationId }), { ...data, date_from: toIso(data.date_from), date_to: toIso(data.date_to) }, {
		preserveScroll: true,
		onSuccess: () => (dialogOpen.value = false),
		onError: (e) => (errors.value = e),
		onFinish: () => (saving.value = false),
	})
}
const deleteLine = (id: number) => router.delete(route("grp.models.epr_manual_line.delete", { organisation: props.organisationId, eprManualLine: id }), { preserveScroll: true })

const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] focus:!border-[--app-accent]"
const selectPt = { label: { class: "!py-1 !text-xs" } }

const change = computed(() => (props.data?.previous_kg ? Math.round(((props.data.kg - props.data.previous_kg) / props.data.previous_kg) * 1000) / 10 : null))
const kg = (value: number | null) => (value == null ? "—" : locale.number(Math.round(value)))
const date = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })
</script>

<template>
	<div class="px-4 py-5 space-y-5 text-sm text-gray-700">
		<div class="flex flex-wrap items-center gap-3">
			<SegmentedToggle v-model="importMethod" :options="importMethods" :ariaLabel="ctrans('How own-brand imports are counted')" />
			<a :href="downloadUrl" class="sm:ml-auto">
				<Button type="tertiary" :icon="['fal', 'download']" :label="ctrans('Download return (csv)')" />
			</a>
		</div>

		<div v-if="!data" class="space-y-3">
			<div class="h-20 rounded-lg bg-gray-100 animate-pulse" />
			<div class="h-40 rounded-lg bg-gray-100 animate-pulse" />
		</div>

		<div v-else-if="!data.has_data" class="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center">
			<FontAwesomeIcon :icon="['fal', 'file-certificate']" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
			<p class="mt-2 font-medium text-gray-900">{{ ctrans("No packaging weights for what moved between :from and :to", { from: date(data.from), to: date(data.to) }) }}</p>
			<p class="mt-1 text-xs text-gray-500">{{ ctrans("The return is worked out from the packaging set on each trade unit. The Completeness tab lists the SKOs still without it.") }}</p>
		</div>

		<template v-else>
			<div class="flex flex-wrap items-end gap-x-8 gap-y-2">
				<div>
					<div class="text-xs text-gray-500">{{ ctrans("Packaging to report") }}</div>
					<div class="flex items-baseline gap-2">
						<span class="text-2xl font-semibold text-gray-900 tabular-nums">{{ ctrans(":kg kg", { kg: kg(data.kg) }) }}</span>
						<span v-if="change !== null" class="text-xs tabular-nums" :class="Math.abs(change) > 30 ? 'text-amber-600' : 'text-gray-500'">
							<FontAwesomeIcon :icon="['fal', change >= 0 ? 'arrow-up' : 'arrow-down']" fixed-width aria-hidden="true" />
							{{ ctrans(":change% vs :kg kg the period before", { change: locale.number(Math.abs(change)), kg: kg(data.previous_kg) }) }}
						</span>
					</div>
				</div>
				<div class="text-xs text-gray-500">
					<template v-if="data.submission_period">{{ ctrans("Submission period :period", { period: data.submission_period }) }}</template>
					<template v-else>{{ ctrans("Not a reporting half-year: the file is for checking only") }}</template>
				</div>
			</div>

			<div class="rounded-lg ring-1 ring-gray-200 bg-white overflow-x-auto">
				<table class="min-w-full text-xs">
					<thead class="text-gray-600">
						<tr>
							<th class="px-4 py-2 text-left font-medium">{{ ctrans("kg") }}</th>
							<th v-for="material in data.materials" :key="material" class="px-3 py-2 text-right font-medium whitespace-nowrap">{{ materialLabels[material] ?? material }}</th>
							<th class="px-4 py-2 text-right font-medium">{{ ctrans("Total") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in data.form" :key="row.activity">
							<th class="px-4 py-2 text-left font-medium text-gray-700 whitespace-nowrap">{{ row.label }}</th>
							<td v-for="material in data.materials" :key="material" class="px-3 py-2 text-right tabular-nums align-top">
								<div class="text-gray-700">{{ kg(row.cells[material].kg) }}</div>
								<div v-if="row.cells[material].change !== null" :class="row.cells[material].warning ? 'text-amber-600' : 'text-gray-400'" v-tooltip="ctrans('Previous period: :kg kg', { kg: kg(row.cells[material].previous_kg) })">
									{{ row.cells[material].change > 0 ? "+" : "" }}{{ locale.number(row.cells[material].change) }}%
								</div>
							</td>
							<td class="px-4 py-2 text-right tabular-nums align-top">
								<div class="font-medium text-gray-900">{{ kg(row.kg) }}</div>
								<div v-if="row.change !== null" :class="row.warning ? 'text-amber-600' : 'text-gray-400'">{{ row.change > 0 ? "+" : "" }}{{ locale.number(row.change) }}%</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<details class="rounded-lg ring-1 ring-gray-200 bg-white">
				<summary class="px-4 py-3 cursor-pointer font-semibold text-gray-900">{{ ctrans("Rows in the file (:count)", { count: data.lines.length }) }}</summary>
				<div class="overflow-x-auto border-t border-gray-200">
					<table class="min-w-full text-xs">
						<thead class="text-gray-600">
							<tr class="text-left">
								<th class="px-4 py-2 font-medium">{{ ctrans("Activity") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Type") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Class") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("Material") }}</th>
								<th class="px-2 py-2 font-medium">{{ ctrans("RAM") }}</th>
								<th class="px-4 py-2 font-medium text-right">{{ ctrans("kg") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 tabular-nums">
							<tr v-for="line in data.lines" :key="`${line.activity}${line.type}${line.class}${line.material}${line.ram}`">
								<td class="px-4 py-1.5">{{ line.activity }}</td>
								<td class="px-2 py-1.5">{{ line.type }}</td>
								<td class="px-2 py-1.5">{{ line.class }}</td>
								<td class="px-2 py-1.5">{{ line.material }}</td>
								<td class="px-2 py-1.5">{{ line.ram ?? "—" }}</td>
								<td class="px-4 py-1.5 text-right">{{ kg(line.kg) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</details>
		</template>

		<div v-if="data" class="rounded-lg ring-1 ring-gray-200 bg-white">
			<div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200">
				<div>
					<h3 class="font-semibold text-gray-900">{{ ctrans("Added by hand") }}</h3>
					<p class="text-xs text-gray-500">{{ ctrans("Packaging no document in Aiku records, such as import cartons and self-managed waste. Each line counts in the period it falls within.") }}</p>
				</div>
				<Button type="tertiary" size="xs" :icon="['fal', 'plus']" :label="ctrans('Add a line')" @click="openDialog" />
			</div>
			<div v-if="!data.manual_lines.length" class="px-4 py-6 text-center text-xs text-gray-500">{{ ctrans("Nothing added by hand for this period.") }}</div>
			<div v-else class="overflow-x-auto">
				<table class="min-w-full text-xs">
					<thead class="text-gray-600">
						<tr class="text-left">
							<th class="px-4 py-2 font-medium">{{ ctrans("Period") }}</th>
							<th class="px-2 py-2 font-medium">{{ ctrans("Activity") }}</th>
							<th class="px-2 py-2 font-medium">{{ ctrans("Type") }}</th>
							<th class="px-2 py-2 font-medium">{{ ctrans("Class") }}</th>
							<th class="px-2 py-2 font-medium">{{ ctrans("Material") }}</th>
							<th class="px-2 py-2 font-medium text-right">{{ ctrans("kg") }}</th>
							<th class="px-2 py-2 font-medium">{{ ctrans("Notes") }}</th>
							<th class="px-4 py-2" />
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 tabular-nums">
						<tr v-for="manual in data.manual_lines" :key="manual.id">
							<td class="px-4 py-2 whitespace-nowrap">{{ date(manual.date_from) }} – {{ date(manual.date_to) }}</td>
							<td class="px-2 py-2">{{ manual.activity }}</td>
							<td class="px-2 py-2">{{ manual.packaging_type }}<span v-if="manual.from_nation" class="text-gray-500"> {{ manual.from_nation }}→{{ manual.to_nation ?? "?" }}</span></td>
							<td class="px-2 py-2">{{ manual.packaging_class }}</td>
							<td class="px-2 py-2">{{ materials[manual.material_category] }}</td>
							<td class="px-2 py-2 text-right">{{ kg(manual.kg) }}</td>
							<td class="px-2 py-2 text-gray-500">{{ manual.notes }}<span v-if="manual.user"> · {{ manual.user }}</span></td>
							<td class="px-4 py-2 text-right"><Button type="transparent" size="xs" :icon="['fal', 'trash-alt']" :tooltip="ctrans('Delete')" @click="deleteLine(manual.id)" /></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<Dialog v-model:visible="dialogOpen" modal :header="ctrans('Add packaging by hand')" class="w-full max-w-lg">
			<div class="space-y-3 text-sm">
				<div class="flex flex-wrap gap-2">
					<Button v-for="preset in presets" :key="preset.label" type="tertiary" size="xs" :label="preset.label" @click="applyPreset(preset)" />
				</div>
				<div class="grid grid-cols-2 gap-3">
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("From") }}</span>
						<DatePicker v-model="line.date_from" dateFormat="d M yy" :manualInput="false" class="w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!py-1 !text-xs' } } }" />
					</label>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("To") }}</span>
						<DatePicker v-model="line.date_to" dateFormat="d M yy" :manualInput="false" class="w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!py-1 !text-xs' } } }" />
					</label>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("Activity") }}</span>
						<Select v-model="line.activity" :options="codeOptions(['SO', 'IM', 'PF'])" optionLabel="label" optionValue="value" class="w-full" :class="fieldFocusClass" :pt="selectPt" />
					</label>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("Packaging type") }}</span>
						<Select v-model="line.packaging_type" :options="codeOptions(['HH', 'NH', 'CW', 'OW', 'HDC', 'NDC'])" optionLabel="label" optionValue="value" class="w-full" :class="fieldFocusClass" :pt="selectPt" />
					</label>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("Class") }}</span>
						<Select v-model="line.packaging_class" :options="codeOptions(['P1', 'P2', 'P3', 'P4'])" optionLabel="label" optionValue="value" class="w-full" :class="fieldFocusClass" :pt="selectPt" />
					</label>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("Material") }}</span>
						<Select v-model="line.material_category" :options="materialOptions" optionLabel="label" optionValue="value" class="w-full" :class="fieldFocusClass" :pt="selectPt" />
					</label>
					<template v-if="['CW', 'OW'].includes(line.packaging_type)">
						<label class="space-y-1">
							<span class="text-xs text-gray-500">{{ ctrans("Waste from") }}</span>
							<Select v-model="line.from_nation" :options="nationOptions" optionLabel="label" optionValue="value" showClear class="w-full" :class="fieldFocusClass" :pt="selectPt" />
						</label>
						<label class="space-y-1">
							<span class="text-xs text-gray-500">{{ ctrans("Waste to") }}</span>
							<Select v-model="line.to_nation" :options="nationOptions" optionLabel="label" optionValue="value" showClear class="w-full" :class="fieldFocusClass" :pt="selectPt" />
						</label>
					</template>
					<label class="space-y-1">
						<span class="text-xs text-gray-500">{{ ctrans("Weight") }}</span>
						<InputNumber v-model="line.kg" :maxFractionDigits="3" suffix=" kg" class="w-full" :class="fieldFocusClass" :pt="{ pcInputText: { root: { class: '!py-1 !text-xs w-full' } } }" :invalid="!!errors.kg" />
					</label>
					<label class="space-y-1 col-span-2">
						<span class="text-xs text-gray-500">{{ ctrans("Notes (e.g. waste transfer note number)") }}</span>
						<InputText v-model="line.notes" class="w-full !py-1 !text-xs" :class="fieldFocusClass" />
					</label>
				</div>
				<div v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors).join(" ") }}</div>
				<div class="flex justify-end">
					<Button type="tertiary" :label="ctrans('Save')" :loading="saving" :disabled="!line.kg" @click="saveLine" />
				</div>
			</div>
		</Dialog>
	</div>
</template>
