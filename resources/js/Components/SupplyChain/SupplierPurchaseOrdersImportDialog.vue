<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import FileUpload from "primevue/fileupload"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faExclamationTriangle, faFileExcel, faTimes, faUndoAlt } from "@fal"
import { faSpinnerThird } from "@fad"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

library.add(faCheck, faExclamationTriangle, faFileExcel, faTimes, faUndoAlt, faSpinnerThird)

interface PlanLine {
	row: number
	supplier_product_id: number
	code: string
	name: string | null
	cartons: number
	units_per_carton: number
	units: number
	current_units: number | null
	unit_cost: number
	amount: number
	warnings: string[]
}

interface OrganisationPlan {
	key: string
	organisation: { id: number; code: string; name: string }
	purchase_order: { id: number | null; reference: string | null; is_new: boolean }
	lines: PlanLine[]
	cartons: number
	units: number
	amount: number
}

interface ImportPlan {
	errors: string[]
	file: { name: string; hash: string; lines_hash: string | null }
	currency: string | null
	organisations: OrganisationPlan[]
	skipped: { row: number | null; code: string; cartons: Record<string, number | string>; reason: string }[]
	rows: number
	rows_without_cartons: number
	previous_imports: { id: number; filename: string | null; user: string | null; imported_at: string | null; match: "same_file" | "same_lines" | "same_name"; purchase_orders: string[] }[]
}

interface ImportResult {
	upload_id: number
	purchase_orders: { organisation: string; reference: string | null; url: string | null; lines: number; errors: string[] }[]
	skipped: number
}

const props = defineProps<{
	previewRoute: routeType
	importRoute: routeType
	orderColumns: { heading: string; organisation: string }[]
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()

const file = ref<File | null>(null)
const plan = ref<ImportPlan | null>(null)
const result = ref<ImportResult | null>(null)
const isLoading = ref(false)
const isImporting = ref(false)
const errorMessages = ref<string[]>([])
const importAgain = ref(false)
const shownTab = ref<string>("")

const reset = () => {
	file.value = null
	plan.value = null
	result.value = null
	errorMessages.value = []
	importAgain.value = false
	shownTab.value = ""
}

watch(visible, (isVisible) => {
	if (!isVisible) {
		reset()
	}
})

const errorsFrom = (error: any, fallback: string): string[] => {
	const errors = error?.response?.data?.errors
	if (errors) {
		return Object.values(errors).flat() as string[]
	}
	return [error?.response?.data?.message || fallback]
}

const formData = (extra: Record<string, string> = {}) => {
	const data = new FormData()
	data.append("file", file.value as File)
	Object.entries(extra).forEach(([key, value]) => data.append(key, value))
	return data
}

const onSelectFile = async (selected: File | undefined) => {
	if (!selected) {
		return
	}
	reset()
	file.value = selected
	isLoading.value = true
	try {
		const { data } = await axios.post<ImportPlan>(route(props.previewRoute.name, props.previewRoute.parameters), formData())
		plan.value = data
		shownTab.value = data.organisations[0]?.key ?? (data.skipped.length ? "skipped" : "")
	} catch (error: any) {
		errorMessages.value = errorsFrom(error, ctrans("The file could not be read, try again"))
	} finally {
		isLoading.value = false
	}
}

const isDuplicate = computed(() => Boolean(plan.value?.previous_imports.length))
const canImport = computed(() => Boolean(plan.value && !plan.value.errors.length && plan.value.organisations.length && (!isDuplicate.value || importAgain.value)) && !isImporting.value)

const submitImport = async () => {
	if (!canImport.value) {
		return
	}
	isImporting.value = true
	errorMessages.value = []
	try {
		const { data } = await axios.post<ImportResult>(route(props.importRoute.name, props.importRoute.parameters), formData({ import_again: importAgain.value ? "1" : "0" }))
		result.value = data
		router.reload({ only: ["data"] })
	} catch (error: any) {
		errorMessages.value = errorsFrom(error, ctrans("The purchase orders could not be created, try again"))
	} finally {
		isImporting.value = false
	}
}

const tabOptions = computed(() => [
	...(plan.value?.organisations ?? []).map((organisationPlan) => ({
		label: organisationPlan.organisation.code,
		value: organisationPlan.key,
		count: organisationPlan.lines.length,
	})),
	...(plan.value?.skipped.length ? [{ label: ctrans("Not imported"), value: "skipped", count: plan.value.skipped.length, tone: "amber" as const }] : []),
])

const shownOrganisation = computed(() => plan.value?.organisations.find((organisationPlan) => organisationPlan.key === shownTab.value) ?? null)

const organisationCodes = computed(() => (plan.value?.organisations ?? []).map((organisationPlan) => organisationPlan.organisation.code).join(", "))

const submitLabel = computed(() => {
	const count = plan.value?.organisations.length ?? 0
	if (!count) {
		return ctrans("Create draft purchase orders")
	}
	return count === 1
		? ctrans("Create 1 purchase order for :organisations", { organisations: organisationCodes.value })
		: ctrans("Create :count purchase orders at once: :organisations", { count, organisations: organisationCodes.value })
})

const money = (amount: number) => (plan.value?.currency ? locale.currencyFormat(plan.value.currency, amount) : locale.number(amount))
const unitCost = (amount: number) => locale.currencyFormatUnitCost(plan.value?.currency, amount)

const matchLabel = (match: ImportPlan["previous_imports"][number]["match"]) => ({
	same_file: ctrans("same file"),
	same_lines: ctrans("same products and cartons"),
	same_name: ctrans("same file name"),
})[match]

const cartonsText = (cartons: Record<string, number | string>) => Object.entries(cartons).map(([key, quantity]) => `${key} ${quantity}`).join(", ")

const isGuideOpen = ref(false)
const showGuide = computed(() => !isLoading.value && (!plan.value || plan.value.errors.length > 0 || isGuideOpen.value))

const columnLetter = (index: number): string => {
	let letter = ""
	for (let number = index + 1; number > 0; number = Math.floor((number - 1) / 26)) {
		letter = String.fromCharCode(65 + ((number - 1) % 26)) + letter
	}
	return letter
}

const exampleColumns = computed(() => [
	{ letter: "B", heading: "Part reference", need: "required", values: ["ABC-01", "ABC-02"] },
	{ letter: "D", heading: "Supplier's product code", need: "recommended", values: ["ABC-01", "ABC-02"] },
	{ letter: "F", heading: "Units per SKO", need: "optional", values: ["12", "1"] },
	{ letter: "H", heading: "SKOs per carton", need: "optional", values: ["12", "60"] },
	...props.orderColumns.map((column, index) => ({
		letter: columnLetter(36 + index),
		heading: column.heading,
		need: "cartons",
		values: index === 0 ? ["14", "6"] : index === 1 ? ["14", ""] : ["", ""],
	})),
])

const needLabel = (need: string) => ({
	required: ctrans("Required"),
	recommended: ctrans("Recommended"),
	optional: ctrans("Optional"),
	cartons: ctrans("Cartons"),
})[need]

const needClass = (need: string) => ({
	required: "bg-red-50 text-red-700",
	recommended: "bg-[--app-accent-soft] text-[--app-accent-deep]",
	optional: "bg-gray-100 text-gray-600",
	cartons: "bg-green-50 text-green-700",
})[need]
</script>

<template>
	<Dialog v-model:visible="visible" modal :header="ctrans('Import purchase orders from Excel')" class="w-full max-w-5xl">
		<div v-if="result" class="space-y-3 text-sm">
			<p class="text-gray-700">{{ ctrans("The cartons are on these draft purchase orders. Check each one and send it from its page.") }}</p>
			<div v-for="purchaseOrder in result.purchase_orders" :key="purchaseOrder.organisation" class="rounded border border-gray-200 px-3 py-2">
				<div class="flex flex-wrap items-center gap-x-3 gap-y-1">
					<span class="font-semibold text-gray-800">{{ purchaseOrder.organisation }}</span>
					<a v-if="purchaseOrder.url" :href="purchaseOrder.url" class="whitespace-nowrap font-medium text-[--app-accent] hover:underline">{{ purchaseOrder.reference }}</a>
					<span class="text-gray-600">{{ ctrans(":count lines", { count: purchaseOrder.lines }) }}</span>
				</div>
				<ul v-if="purchaseOrder.errors.length" class="mt-1 list-disc pl-5 text-xs text-red-600">
					<li v-for="(error, index) in purchaseOrder.errors" :key="index">{{ error }}</li>
				</ul>
			</div>
			<p v-if="result.skipped" class="text-xs text-amber-700">{{ ctrans(":count rows were not imported, as listed in the preview.", { count: result.skipped }) }}</p>
		</div>

		<div v-else class="space-y-4 text-sm">
			<div class="flex flex-wrap items-center gap-3">
				<FileUpload
					mode="basic"
					:auto="false"
					customUpload
					accept=".xlsx,.xls"
					:chooseButtonProps="{ severity: 'secondary', outlined: true, size: 'small' }"
					:chooseLabel="file ? ctrans('Choose another file') : ctrans('Choose Excel file')"
					:disabled="isLoading || isImporting"
					@select="(event) => onSelectFile(event.files?.[0])"
				/>
				<span v-if="file" class="flex items-center gap-1 text-xs text-gray-600">
					<FontAwesomeIcon icon="fal fa-file-excel" class="text-green-600" fixed-width aria-hidden="true" />
					{{ file.name }}
				</span>
			</div>

			<div class="border-b border-gray-400 border-dashed">

			</div>

			<button
				v-if="plan && !plan.errors.length && !isLoading"
				type="button"
				class="text-xs font-medium text-[--app-accent] hover:underline"
				@click="isGuideOpen = !isGuideOpen"
			>
				{{ isGuideOpen ? ctrans("Hide which columns are read") : ctrans("Which columns are read?") }}
			</button>

			<div v-if="showGuide" class="space-y-3 rounded border border-gray-200 bg-gray-50/60 p-3">
				<div>
					<div class="text-xs font-semibold text-gray-700">{{ ctrans("What to upload") }}</div>
					<p class="mt-0.5 text-xs text-gray-600">
						{{ ctrans("The AW supplier parts template (.xlsx or .xls), tab \"Product data\" or else the first tab. Columns are found by their heading, in any position and order; other columns and tabs are ignored.") }}
					</p>
				</div>

				<div class="overflow-x-auto rounded border border-gray-300 bg-white">
					<table class="text-xs">
						<thead class="whitespace-nowrap">
							<tr class="bg-gray-100 text-center text-[10px] text-gray-400">
								<th class="w-8 border-b border-r border-gray-300 font-normal"></th>
								<th v-for="column in exampleColumns" :key="column.heading" class="border-b border-r border-gray-300 px-2 font-normal">{{ column.letter }}</th>
							</tr>
							<tr class="text-left">
								<th class="border-b border-r border-gray-300 bg-gray-100 text-center text-[10px] font-normal text-gray-400">5</th>
								<th v-for="column in exampleColumns" :key="column.heading" class="border-b border-r border-gray-200 px-2 py-1 align-top">
									<div class="font-semibold text-gray-800">{{ column.heading }}</div>
									<span class="mt-0.5 inline-block rounded px-1 text-[10px] font-medium" :class="needClass(column.need)">{{ needLabel(column.need) }}</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="rowIndex in [0, 1]" :key="rowIndex">
								<td class="border-b border-r border-gray-300 bg-gray-100 text-center text-[10px] text-gray-400">{{ 6 + rowIndex }}</td>
								<td v-for="column in exampleColumns" :key="column.heading" class="whitespace-nowrap border-b border-r border-gray-100 px-2 py-0.5 text-gray-600" :class="{ 'text-right': column.need !== 'required' && column.need !== 'recommended' }">
									{{ column.values[rowIndex] }}
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<dl class="grid grid-cols-1 gap-x-4 gap-y-1.5 text-xs sm:grid-cols-[max-content_1fr]">
					<dt class="whitespace-nowrap font-semibold text-gray-700">Part reference</dt>
					<dd class="text-gray-600">{{ ctrans("Required: marks the heading row, which must be in the first 20 rows. Used as the code when the supplier's code does not match.") }}</dd>

					<dt class="whitespace-nowrap font-semibold text-gray-700">Supplier's product code</dt>
					<dd class="text-gray-600">{{ ctrans("Matched first against this supplier's product codes. Any apostrophe works: ' ’ ` or none.") }}</dd>

					<dt class="whitespace-nowrap font-semibold text-gray-700">Order Cartons …</dt>
					<dd class="text-gray-600">
						{{ ctrans("At least one, in whole cartons; empty or 0 is not ordered. Each column becomes a draft purchase order of its organisation:") }}
						<span v-for="(column, index) in orderColumns" :key="column.heading" class="whitespace-nowrap">
							<span class="font-medium text-gray-800">{{ column.heading }}</span> → {{ column.organisation }}<template v-if="index < orderColumns.length - 1">, </template>
						</span>
					</dd>

					<dt class="whitespace-nowrap font-semibold text-gray-700">Units per SKO, SKOs per carton</dt>
					<dd class="text-gray-600">{{ ctrans("Optional, only compared with the carton in Aiku. Units ordered are always cartons × the units per carton in Aiku.") }}</dd>
				</dl>

				<p class="text-xs text-gray-500">{{ ctrans("Nothing is created until you confirm the preview, and nothing is sent to the supplier.") }}</p>
			</div>

			<div v-if="isLoading" class="flex items-center gap-2 py-6 text-gray-500">
				<FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
				{{ ctrans("Reading the file…") }}
			</div>

			<template v-else-if="plan">
				<ul v-if="plan.errors.length" class="list-disc space-y-0.5 pl-5 text-xs text-red-600">
					<li v-for="(error, index) in plan.errors" :key="index">{{ error }}</li>
				</ul>

				<template v-else>
					<div v-if="isDuplicate" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900">
						<div class="flex items-center gap-2 font-semibold">
							<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-600" fixed-width aria-hidden="true" />
							{{ ctrans("This file looks already imported") }}
						</div>
						<ul class="mt-1 space-y-0.5 text-xs">
							<li v-for="previous in plan.previous_imports" :key="previous.id">
								{{ ctrans(":file by :user on :date (:match)", { file: previous.filename ?? "-", user: previous.user ?? "-", date: useFormatTime(previous.imported_at ?? undefined, { formatTime: "hm" }), match: matchLabel(previous.match) }) }}
								<span v-if="previous.purchase_orders.length" class="whitespace-nowrap font-medium">· {{ previous.purchase_orders.join(", ") }}</span>
							</li>
						</ul>
						<label class="mt-2 flex items-center gap-2 text-xs">
							<Checkbox v-model="importAgain" binary inputId="supplier-purchase-orders-import-again" />
							<span>{{ ctrans("Import it again: lines already on an open draft take the quantity of this file") }}</span>
						</label>
					</div>

					<div class="text-gray-700">
						{{ ctrans(":rows rows read, :without without cartons. :orders purchase orders, :skipped rows not imported.", { rows: plan.rows, without: plan.rows_without_cartons, orders: plan.organisations.length, skipped: plan.skipped.length }) }}
					</div>

					<SegmentedToggle v-if="tabOptions.length" v-model="shownTab" :options="tabOptions" :ariaLabel="ctrans('Organisation')" />

					<div v-if="shownOrganisation" class="space-y-2">
						<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-gray-700">
							<span class="font-semibold text-gray-800">{{ shownOrganisation.organisation.name }}</span>
							<span v-if="shownOrganisation.purchase_order.is_new">{{ ctrans("New draft purchase order") }}</span>
							<span v-else>
								{{ ctrans("Added to open draft") }}
								<span class="whitespace-nowrap font-medium">{{ shownOrganisation.purchase_order.reference }}</span>
							</span>
							<span>{{ ctrans(":cartons cartons, :units units", { cartons: locale.number(shownOrganisation.cartons), units: locale.number(shownOrganisation.units) }) }}</span>
							<span class="font-semibold">{{ money(shownOrganisation.amount) }}</span>
						</div>
						<div class="max-h-[50vh] overflow-auto rounded border border-gray-200">
							<table class="w-full text-xs">
								<thead class="sticky top-0 whitespace-nowrap bg-gray-50 text-left text-gray-600">
									<tr>
										<th class="px-2 py-1 font-semibold">{{ ctrans("Row") }}</th>
										<th class="px-2 py-1 font-semibold">{{ ctrans("Code") }}</th>
										<th class="px-2 py-1 font-semibold">{{ ctrans("Name") }}</th>
										<th class="px-2 py-1 text-right font-semibold">{{ ctrans("Cartons") }}</th>
										<th class="px-2 py-1 text-right font-semibold">{{ ctrans("Units / carton") }}</th>
										<th class="px-2 py-1 text-right font-semibold">{{ ctrans("Units") }}</th>
										<th class="px-2 py-1 text-right font-semibold">{{ ctrans("Unit cost") }}</th>
										<th class="px-2 py-1 text-right font-semibold">{{ ctrans("Amount") }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="line in shownOrganisation.lines" :key="line.supplier_product_id" class="border-t border-gray-100 align-top">
										<td class="px-2 py-1 text-gray-500">{{ line.row }}</td>
										<td class="whitespace-nowrap px-2 py-1 font-medium text-gray-800">{{ line.code }}</td>
										<td class="px-2 py-1 text-gray-600">
											{{ line.name }}
											<div v-if="line.current_units !== null" class="text-amber-700">{{ ctrans("Already on the draft with :units units, replaced by :new", { units: locale.number(line.current_units), new: locale.number(line.units) }) }}</div>
											<div v-for="(warning, index) in line.warnings" :key="index" class="text-amber-700">{{ warning }}</div>
										</td>
										<td class="px-2 py-1 text-right">{{ locale.number(line.cartons) }}</td>
										<td class="px-2 py-1 text-right text-gray-500">{{ locale.number(line.units_per_carton) }}</td>
										<td class="px-2 py-1 text-right">{{ locale.number(line.units) }}</td>
										<td class="whitespace-nowrap px-2 py-1 text-right text-gray-500">{{ unitCost(line.unit_cost) }}</td>
										<td class="whitespace-nowrap px-2 py-1 text-right">{{ money(line.amount) }}</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div v-else-if="shownTab === 'skipped'" class="max-h-[50vh] overflow-auto rounded border border-amber-200">
						<table class="w-full text-xs">
							<thead class="sticky top-0 whitespace-nowrap bg-amber-50 text-left text-amber-800">
								<tr>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Row") }}</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Code") }}</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Cartons") }}</th>
									<th class="px-2 py-1 font-semibold">{{ ctrans("Reason") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="(skipped, index) in plan.skipped" :key="index" class="border-t border-amber-100 align-top">
									<td class="px-2 py-1 text-gray-500">{{ skipped.row ?? "-" }}</td>
									<td class="whitespace-nowrap px-2 py-1 font-medium text-gray-800">{{ skipped.code || "-" }}</td>
									<td class="whitespace-nowrap px-2 py-1 text-gray-600">{{ cartonsText(skipped.cartons) }}</td>
									<td class="px-2 py-1 text-gray-600">{{ skipped.reason }}</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div v-if="plan.organisations.length" class="rounded border border-green-200 bg-green-50/60 px-3 py-2">
						<div class="text-xs font-semibold text-gray-800">
							{{ plan.organisations.length === 1
								? ctrans("Confirming creates 1 purchase order:")
								: ctrans("Confirming creates :count purchase orders at once, one per organisation:", { count: plan.organisations.length }) }}
						</div>
						<ul class="mt-1 space-y-0.5 text-xs text-gray-700">
							<li v-for="organisationPlan in plan.organisations" :key="organisationPlan.key" class="flex flex-wrap items-baseline gap-x-2">
								<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
								<span class="whitespace-nowrap font-semibold text-gray-800">{{ organisationPlan.organisation.code }}</span>
								<span class="text-gray-500">{{ organisationPlan.organisation.name }}</span>
								<span>·</span>
								<span v-if="organisationPlan.purchase_order.is_new">{{ ctrans("new draft") }}</span>
								<span v-else>{{ ctrans("added to open draft") }} <span class="whitespace-nowrap font-medium">{{ organisationPlan.purchase_order.reference }}</span></span>
								<span>·</span>
								<span>{{ ctrans(":lines products, :cartons cartons", { lines: organisationPlan.lines.length, cartons: locale.number(organisationPlan.cartons) }) }}</span>
								<span>·</span>
								<span class="whitespace-nowrap font-medium">{{ money(organisationPlan.amount) }}</span>
							</li>
						</ul>
						<p class="mt-1 text-[11px] text-gray-500">{{ ctrans("They stay as drafts: check and send each one from its page.") }}</p>
					</div>

					<p v-if="!plan.organisations.length" class="text-xs text-red-600">{{ ctrans("Nothing to import: no row has cartons for a product this supplier sells.") }}</p>
				</template>
			</template>
		</div>

		<ul v-if="errorMessages.length" class="mt-3 list-disc pl-5 text-xs text-red-600">
			<li v-for="(error, index) in errorMessages" :key="index">{{ error }}</li>
		</ul>

		<template #footer>
			<Button type="tertiary" :label="ctrans('Close')" @click="visible = false">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button v-if="result" type="tertiary" :label="ctrans('Import another file')" @click="reset">
				<template #icon>
					<FontAwesomeIcon icon="fal fa-undo-alt" class="text-gray-500" fixed-width aria-hidden="true" />
				</template>
			</Button>
			<Button
				v-else
				type="tertiary"
				:label="submitLabel"
				:disabled="!canImport"
				:loading="isImporting"
				@click="submitImport"
			>
				<template #icon>
					<FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</template>
	</Dialog>
</template>
