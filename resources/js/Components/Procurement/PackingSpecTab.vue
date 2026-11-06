<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-20 10:35
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"
import Select from "primevue/select"
import MultiSelect from "primevue/multiselect"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import DatePicker from "primevue/datepicker"
import FileUpload from "primevue/fileupload"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBoxOpen, faCheck, faTimes, faPlus, faTrashAlt, faPrint, faUndo, faExclamationTriangle, faCopy, faCamera, faPencil, faListOl, faGripVertical } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import draggable from "vuedraggable"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type Level = "per_unit" | "per_pack" | "per_carton"

type SpecLine = {
	id?: number
	packaging_component_id: number
	label: string
	level: Level
	role: string
	quantity: number
	weight_g_each: number | null
	declared_weight_g_each: number | null
	weight_is_measured: boolean
	fate_at_our_warehouse: string
	is_epr_reportable: boolean
	description: string | null
	grams_per_carton: number
}

type Spec = {
	id: number
	version: number
	status: string
	status_label: string
	effective_from: string
	packed_by: string
	carton_length_mm: number | null
	carton_width_mm: number | null
	carton_height_mm: number | null
	declared_carton_gross_weight_g: number | null
	measured_carton_gross_weight_g: number | null
	packaging_weight_g: number | null
	weight_check_deviation_pct: number | null
	needs_review: boolean
	review_reason: string | null
	materials_weighed: boolean
	return_reason: string | null
	returned_at: string | null
	returned_by: string | null
	verified_at: string | null
	verified_by: string | null
	photo: { url: string; download_url: string } | null
	lines: SpecLine[]
	return_route: routeType | null
	verify_route: routeType | null
}

type DropdownItem = {
	id: number
	key: string
	label: string
	labels: Record<string, string>
	default_role: string | null
	material: string
	is_epr_reportable: boolean
	needs_review: boolean
}

const props = defineProps<{
	data: {
		is_agent: boolean
		tolerance_pct: number
		supplier_product: {
			code: string
			name: string | null
			units_per_pack: number | null
			units_per_carton: number | null
			carton_weight: number | null
			carton_net_weight: number | null
			unit_net_weight_g: number | null
			carton_dimensions: { l: number; w: number; h: number } | null
		}
		live: Spec | null
		versions: {
			id: number
			version: number
			status: string
			status_label: string
			effective_from: string
			submitted_by: string | null
			submitted_at: string | null
			lines: number
			packaging_g: number | null
			return_reason: string | null
			pdf_url: string
		}[]
		dropdown: DropdownItem[]
		options: {
			levels: Record<Level, string>
			roles: Record<string, string>
			packed_by: Record<string, string>
			fates: Record<string, string>
		}
		copy_candidates: { id: number; code: string; name: string | null; has_spec: boolean }[]
		store_route: routeType | null
		instructions: string[]
		instructions_route: routeType | null
		purchase_orders: { label: string; url: string }[]
	}
	tab: string
}>()

const locale = useLocaleStore()

const levels: Level[] = ["per_unit", "per_pack", "per_carton"]
const levelInformation: Record<Level, string> = {
	per_unit: ctrans("Packing put around each single unit, e.g. a bubble wrap or a polybag for every item. Not the product itself. The quantity is per unit and is multiplied by the units in the carton."),
	per_pack: ctrans("Packing that groups units into one SKO (pack), following the units per pack of this supplier product, e.g. an inner box or a polybag holding the SKO. The quantity is per SKO and is multiplied by the SKOs in the carton."),
	per_carton: ctrans("Packing used once for the whole master carton, e.g. the corrugated carton, tape, strapping, void fill or desiccant. The quantity is per carton."),
}
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
const chooseFilePt = { pcChooseButton: { root: { class: "!border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50" } } }

const dropdownById = computed(() => Object.fromEntries(props.data.dropdown.map((item) => [item.id, item])))
const roleOptions = computed(() => Object.entries(props.data.options.roles).map(([value, label]) => ({ value, label })))
const packedByOptions = computed(() => Object.entries(props.data.options.packed_by).map(([value, label]) => ({ value, label })))
const copyOptions = computed(() => props.data.copy_candidates.map((product) => ({
	...product,
	label: `${product.code}${product.name ? " · " + product.name : ""}${product.has_spec ? " ✓" : ""}`,
})))

const formatNumber = (value: number | null | undefined, digits = 1) => (value === null || value === undefined ? "—" : locale.number(Math.round(Number(value) * 10 ** digits) / 10 ** digits))
const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString() : "—")
const toDate = (iso: string | null) => {
	if (!iso) return null
	const [year, month, day] = iso.slice(0, 10).split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toIso = (date: Date | null) => (date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null)

const statusClass = (status: string) =>
	({
		verified: "border-green-300 bg-green-50 text-green-700",
		returned: "border-amber-300 bg-amber-50 text-amber-700",
		submitted: "border-gray-300 bg-gray-50 text-gray-700",
		superseded: "border-gray-200 bg-white text-gray-400",
	})[status] ?? "border-gray-300 bg-gray-50 text-gray-700"

type FormLine = {
	packaging_component_id: number | null
	level: Level
	role: string | null
	quantity: number | null
	weight_g_each: number | null
	description: string | null
}

const blankLine = (level: Level): FormLine => ({ packaging_component_id: null, level, role: null, quantity: null, weight_g_each: null, description: null })

const form = useForm<{
	packed_by: string
	effective_from: Date | null
	carton_length_cm: number | null
	carton_width_cm: number | null
	carton_height_cm: number | null
	declared_carton_gross_weight_g: number | null
	lines: FormLine[]
	photo: File | null
	copy_to: number[]
}>({
	packed_by: "agent",
	effective_from: new Date(),
	carton_length_cm: null,
	carton_width_cm: null,
	carton_height_cm: null,
	declared_carton_gross_weight_g: null,
	lines: [],
	photo: null,
	copy_to: [],
})

const editing = ref(false)

const startEditing = () => {
	const live = props.data.live
	const dimensions = props.data.supplier_product.carton_dimensions
	form.clearErrors()
	form.packed_by = live?.packed_by ?? (props.data.is_agent ? "agent" : "supplier")
	form.effective_from = new Date()
	form.carton_length_cm = live?.carton_length_mm ? live.carton_length_mm / 10 : (dimensions?.l ?? null)
	form.carton_width_cm = live?.carton_width_mm ? live.carton_width_mm / 10 : (dimensions?.w ?? null)
	form.carton_height_cm = live?.carton_height_mm ? live.carton_height_mm / 10 : (dimensions?.h ?? null)
	form.declared_carton_gross_weight_g = live?.declared_carton_gross_weight_g ?? props.data.supplier_product.carton_weight ?? null
	form.lines = live
		? live.lines.map((line) => ({
				packaging_component_id: line.packaging_component_id,
				level: line.level,
				role: line.role,
				quantity: line.quantity,
				weight_g_each: line.weight_g_each,
				description: line.description,
			}))
		: [{ ...blankLine("per_carton"), packaging_component_id: props.data.dropdown.find((item) => item.key === "master_carton")?.id ?? null, quantity: 1 }]
	form.photo = null
	form.copy_to = []
	form.defaults()
	editing.value = true
}

const linesOf = (level: Level) => form.lines.map((line, index) => ({ line, index })).filter(({ line }) => line.level === level)
const addLine = (level: Level) => form.lines.push(blankLine(level))
const removeLine = (index: number) => form.lines.splice(index, 1)

const onMaterialChange = (line: FormLine) => {
	const item = line.packaging_component_id ? dropdownById.value[line.packaging_component_id] : null
	line.role = item?.default_role ?? line.role
}

const unitsPerCarton = computed(() => Math.max(1, props.data.supplier_product.units_per_carton || 1))
const packsPerCarton = computed(() => unitsPerCarton.value / Math.max(1, props.data.supplier_product.units_per_pack || 1))

const perCarton = (line: FormLine) => {
	const quantity = Number(line.quantity ?? 0)
	if (line.level === "per_unit") return quantity * unitsPerCarton.value
	if (line.level === "per_pack") return quantity * packsPerCarton.value
	return quantity
}

const packagingGrams = computed(() => form.lines.reduce((total, line) => total + perCarton(line) * Number(line.weight_g_each ?? 0), 0))

const cartonNetGrams = computed(() => {
	const { carton_net_weight, unit_net_weight_g } = props.data.supplier_product
	if (carton_net_weight) return carton_net_weight
	return unit_net_weight_g ? unit_net_weight_g * unitsPerCarton.value : null
})

const weightCheck = computed(() => {
	const gross = props.data.supplier_product.carton_weight || form.declared_carton_gross_weight_g
	const net = cartonNetGrams.value
	if (!gross || !net) return null
	const expected = net + packagingGrams.value
	const deviation = ((expected - gross) / gross) * 100
	return { expected, cartonWeight: gross, deviation, warning: Math.abs(deviation) > props.data.tolerance_pct }
})

const toMillimetres = (centimetres: number | null) => (centimetres ? Math.round(centimetres * 10) : null)
const toCentimetres = (millimetres: number | null) => (millimetres ? formatNumber(millimetres / 10) : "—")

const lineError = (index: number, field: string) => (form.errors as Record<string, string>)[`lines.${index}.${field}`]
const lineFieldMissing = (index: number, field: keyof FormLine) => !!lineError(index, field) && !form.lines[index]?.[field]
const shakeClass = (index: number, field: keyof FormLine) => (lineFieldMissing(index, field) ? "errorShake rounded-md" : "")

const submit = () => {
	const storeRoute = props.data.store_route
	if (!storeRoute) return
	form
		.transform(({ carton_length_cm, carton_width_cm, carton_height_cm, ...data }) => ({
			...data,
			carton_length_mm: toMillimetres(carton_length_cm),
			carton_width_mm: toMillimetres(carton_width_cm),
			carton_height_mm: toMillimetres(carton_height_cm),
			effective_from: toIso(data.effective_from),
			...(data.photo ? { photo: data.photo } : { photo: null }),
		}))
		.post(route(storeRoute.name, storeRoute.parameters), {
			forceFormData: true,
			preserveScroll: true,
			onSuccess: () => {
				editing.value = false
				router.reload({ only: [props.tab] })
			},
		})
}

type Step = { key: number; text: string }

const instructionsForm = useForm<{ steps: Step[]; copy_to: number[] }>({ steps: [], copy_to: [] })
const editingInstructions = ref(false)
let nextStepKey = 0
const newStep = (text = ""): Step => ({ key: nextStepKey++, text })

const startEditingInstructions = () => {
	instructionsForm.clearErrors()
	instructionsForm.steps = props.data.instructions.length ? props.data.instructions.map((text) => newStep(text)) : [newStep()]
	instructionsForm.copy_to = []
	instructionsForm.defaults()
	editingInstructions.value = true
}

const stepError = (index: number) => (instructionsForm.errors as Record<string, string>)[`instructions.${index}`]

const saveInstructions = () => {
	const instructionsRoute = props.data.instructions_route
	if (!instructionsRoute) return
	instructionsForm.transform(({ steps, copy_to }) => ({ instructions: steps.map((step) => step.text), copy_to })).patch(route(instructionsRoute.name, instructionsRoute.parameters), {
		preserveScroll: true,
		onSuccess: () => {
			editingInstructions.value = false
			router.reload({ only: [props.tab] })
		},
	})
}

const selectedPurchaseOrder = ref<string | null>(props.data.purchase_orders[0]?.url ?? null)
const printPackingInstructions = () => {
	if (selectedPurchaseOrder.value) window.open(selectedPurchaseOrder.value, "_blank")
}

const returnDialog = ref(false)
const returnForm = useForm<{ reason: string }>({ reason: "" })

const submitReturn = () => {
	const returnRoute = props.data.live?.return_route
	if (!returnRoute) return
	returnForm.post(route(returnRoute.name, returnRoute.parameters), {
		preserveScroll: true,
		onSuccess: () => {
			returnDialog.value = false
			returnForm.reset()
			router.reload({ only: [props.tab] })
		},
	})
}

const verify = () => {
	const verifyRoute = props.data.live?.verify_route
	if (!verifyRoute) return
	router.post(route(verifyRoute.name, verifyRoute.parameters), {}, { preserveScroll: true, onSuccess: () => router.reload({ only: [props.tab] }) })
}

const liveLinesOf = (level: Level) => props.data.live?.lines.filter((line) => line.level === level) ?? []
</script>

<template>
	<div class="max-w-6xl space-y-5 px-6 py-5 text-sm">
		<!-- Packing instructions -->
		<div class="space-y-3 rounded border border-gray-200 p-3">
			<div class="flex flex-wrap items-center gap-2">
				<FontAwesomeIcon :icon="faListOl" class="text-gray-400" fixed-width aria-hidden="true" />
				<span class="font-semibold text-gray-800">{{ ctrans("Packing instructions") }}</span>
				<span class="text-xs text-gray-500">{{ ctrans("The steps the packers follow, printed with each purchase order") }}</span>
				<Button v-if="data.instructions_route && !editingInstructions" class="ml-auto" type="tertiary" size="xs" :label="ctrans('Edit steps')" @click="startEditingInstructions">
					<template #icon><FontAwesomeIcon :icon="faPencil" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>

			<template v-if="!editingInstructions">
				<ol v-if="data.instructions.length" class="list-decimal space-y-0.5 pl-5 text-gray-800">
					<li v-for="(step, index) in data.instructions" :key="index">{{ step }}</li>
				</ol>
				<div v-else class="text-xs text-gray-400">{{ ctrans("No packing instructions yet") }}</div>
			</template>

			<div v-else class="space-y-2">
				<draggable v-model="instructionsForm.steps" item-key="key" handle=".step-handle" animation="200" class="space-y-1">
					<template #item="{ element, index }">
						<div class="flex items-center gap-1">
							<span class="step-handle cursor-grab px-1 text-gray-400 hover:text-gray-600 active:cursor-grabbing" v-tooltip="ctrans('Drag to reorder')">
								<FontAwesomeIcon :icon="faGripVertical" fixed-width aria-hidden="true" />
							</span>
							<span class="w-5 text-right text-xs text-gray-500">{{ index + 1 }}.</span>
							<InputText v-model="element.text" :placeholder="ctrans('e.g. Check the goods')" :class="[fieldFocusClass, stepError(index) && !element.text ? 'errorShake rounded-md' : '']" class="flex-1" size="small" />
							<Button type="tertiary" size="xs" :aria-label="ctrans('Remove')" @click="instructionsForm.steps.splice(index, 1)">
								<template #icon><FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" /></template>
							</Button>
						</div>
					</template>
				</draggable>
				<Button type="dashed" size="xs" :label="ctrans('Add step')" @click="instructionsForm.steps.push(newStep())">
					<template #icon><FontAwesomeIcon :icon="faPlus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
				</Button>
				<div v-if="data.copy_candidates.length" class="max-w-xl space-y-1">
					<span class="block text-xs text-gray-500">
						<FontAwesomeIcon :icon="faCopy" fixed-width aria-hidden="true" />
						{{ ctrans("Same steps for other products of this supplier (optional)") }}
					</span>
					<MultiSelect v-model="instructionsForm.copy_to" :options="copyOptions" optionLabel="label" optionValue="id" filter display="chip" :maxSelectedLabels="5" :placeholder="ctrans('Choose products')" :class="fieldFocusClass" fluid />
					<p class="text-[11px] text-gray-500">{{ ctrans("Their steps are replaced by these ones.") }}</p>
				</div>
				<div v-if="instructionsForm.errors.instructions" class="text-xs text-red-600">{{ instructionsForm.errors.instructions }}</div>
				<div class="flex gap-2">
					<Button type="tertiary" :label="ctrans('Cancel')" @click="editingInstructions = false">
						<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<div class="relative">
						<Button type="secondary" :label="ctrans('Save steps')" :loading="instructionsForm.processing" @click="saveInstructions">
							<template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
						</Button>
						<PingIcon v-if="instructionsForm.isDirty" class="text-[7px] text-red-500 !absolute -top-0.5 -right-0.5" v-tooltip="ctrans('Not saved yet')" aria-hidden="true" />
					</div>
				</div>
			</div>

			<div class="flex flex-wrap items-center gap-2 border-t border-gray-300 pt-3">
				<template v-if="data.purchase_orders.length">
					<Select v-model="selectedPurchaseOrder" :options="data.purchase_orders" optionLabel="label" optionValue="url" filter :placeholder="ctrans('Purchase order')" :class="fieldFocusClass" class="min-w-72" size="small" />
					<Button type="tertiary" size="xs" :label="ctrans('Print packing instructions')" :disabled="!selectedPurchaseOrder" @click="printPackingInstructions">
						<template #icon><FontAwesomeIcon :icon="faPrint" class="text-gray-500" fixed-width aria-hidden="true" /></template>
					</Button>
				</template>
				<span v-else class="text-xs text-gray-400">{{ ctrans("No open purchase order with this product to print packing instructions for") }}</span>
			</div>
		</div>

		<!-- Live spec header -->
		<div v-if="data.live && !editing" class="space-y-3">
			<div class="flex flex-wrap items-center gap-3">
				<FontAwesomeIcon :icon="faBoxOpen" class="text-gray-400" fixed-width aria-hidden="true" />
				<span class="font-semibold text-gray-800">{{ ctrans("Packing spec") }} v{{ data.live.version }}</span>
				<span class="rounded border px-2 py-0.5 text-xs" :class="statusClass(data.live.status)">{{ data.live.status_label }}</span>
				<span class="text-xs text-gray-500">{{ ctrans("Effective from :date", { date: formatDate(data.live.effective_from) }) }}</span>
				<span v-if="data.live.verified_at" class="text-xs text-gray-500">{{ ctrans("Verified by :name on :date", { name: data.live.verified_by ?? "—", date: formatDate(data.live.verified_at) }) }}</span>
				<div class="ml-auto flex flex-wrap gap-2">
					<a :href="data.versions.find((version) => version.id === data.live?.id)?.pdf_url" target="_blank" rel="noopener">
						<Button type="tertiary" :label="ctrans('Print packing spec')">
							<template #icon><FontAwesomeIcon :icon="faPrint" class="text-gray-500" fixed-width aria-hidden="true" /></template>
						</Button>
					</a>
					<Button v-if="data.store_route" type="tertiary" :label="ctrans('Change packing')" @click="startEditing">
						<template #icon><FontAwesomeIcon :icon="faPencil" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button v-if="data.live.verify_route" type="tertiary" :label="ctrans('Verify')" @click="verify">
						<template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button v-if="data.live.return_route" type="tertiary" :label="ctrans('Return to agent')" @click="returnDialog = true">
						<template #icon><FontAwesomeIcon :icon="faUndo" class="text-amber-500" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</div>

			<div v-if="data.live.status === 'returned'" class="flex gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
				<div>
					<div class="font-medium">{{ ctrans("Returned to the agent on :date by :name. Correct the packing and save a new version.", { date: formatDate(data.live.returned_at), name: data.live.returned_by ?? "—" }) }}</div>
					<div class="whitespace-pre-line">{{ data.live.return_reason }}</div>
				</div>
			</div>
			<div v-if="data.live.needs_review" class="flex gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-amber-800">
				<FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
				<span>{{ ctrans("Waiting for review: :reason", { reason: data.live.review_reason ?? "" }) }}</span>
			</div>

			<div class="grid gap-4 md:grid-cols-3">
				<div class="space-y-1 rounded border border-gray-200 p-3 text-xs text-gray-600">
					<div>{{ ctrans("Packed by") }}: <span class="text-gray-800">{{ data.options.packed_by[data.live.packed_by] }}</span></div>
					<div>{{ ctrans("Units per pack") }}: <span class="text-gray-800">{{ data.supplier_product.units_per_pack ?? "—" }}</span></div>
					<div>{{ ctrans("Units per carton") }}: <span class="text-gray-800">{{ data.supplier_product.units_per_carton ?? "—" }}</span></div>
					<div>
						{{ ctrans("Carton") }}:
						<span class="text-gray-800">
							<template v-if="data.live.carton_length_mm">{{ toCentimetres(data.live.carton_length_mm) }} × {{ toCentimetres(data.live.carton_width_mm) }} × {{ toCentimetres(data.live.carton_height_mm) }} cm</template>
							<template v-else>—</template>
						</span>
					</div>
					<div>{{ ctrans("Declared gross weight") }}: <span class="text-gray-800">{{ data.live.declared_carton_gross_weight_g ? formatNumber(data.live.declared_carton_gross_weight_g / 1000, 2) + " kg" : "—" }}</span></div>
					<div v-if="data.live.measured_carton_gross_weight_g">{{ ctrans("Measured gross weight") }}: <span class="text-gray-800">{{ formatNumber(data.live.measured_carton_gross_weight_g / 1000, 2) }} kg</span></div>
				</div>
				<div class="space-y-1 rounded border border-gray-200 p-3 text-xs text-gray-600">
					<div>{{ ctrans("Packing material per carton") }}: <span class="text-gray-800">{{ formatNumber(data.live.packaging_weight_g) }} g</span></div>
					<div v-if="data.live.weight_check_deviation_pct !== null" :class="Math.abs(data.live.weight_check_deviation_pct) > data.tolerance_pct ? 'text-amber-700' : ''">
						{{ ctrans("Packing + net weight against the carton weight") }}: <span class="font-medium">{{ data.live.weight_check_deviation_pct > 0 ? "+" : "" }}{{ formatNumber(data.live.weight_check_deviation_pct) }}%</span>
						<span v-if="Math.abs(data.live.weight_check_deviation_pct) > data.tolerance_pct"> · {{ ctrans("over ±:pct%", { pct: data.tolerance_pct }) }}</span>
					</div>
					<div>{{ data.live.materials_weighed ? ctrans("Materials weighed by goods in") : ctrans("Weights as declared, not weighed yet") }}</div>
				</div>
				<div class="space-y-2 rounded border border-gray-200 p-3">
					<div class="text-xs font-semibold text-gray-700">{{ ctrans("Photo of an opened carton") }}</div>
					<template v-if="data.live.photo">
						<a :href="data.live.photo.url" target="_blank" rel="noopener" v-tooltip="ctrans('Open full size')">
							<img :src="data.live.photo.url" class="max-h-40 rounded object-contain" :alt="ctrans('Opened carton')" />
						</a>
						<p class="text-[11px] text-gray-500">{{ ctrans("How a carton of this version is packed. Printed on the packing instruction and checked by goods in on arrival.") }}</p>
					</template>
					<div v-else class="text-xs text-gray-400">{{ ctrans("No photo") }}</div>
				</div>
			</div>

			<div class="grid gap-4 md:grid-cols-3">
				<div v-for="level in levels" :key="level" class="rounded border border-gray-200">
					<div class="border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold text-gray-700">
						{{ data.options.levels[level] }}
						<InformationIcon :information="levelInformation[level]" class="ml-0.5 font-normal" />
					</div>
					<table class="w-full text-xs">
						<thead class="whitespace-nowrap text-gray-500">
							<tr>
								<th class="px-3 py-1 text-left font-normal">{{ ctrans("Material") }}</th>
								<th class="px-2 py-1 text-right font-normal">{{ ctrans("Qty") }}</th>
								<th class="px-3 py-1 text-right font-normal">{{ ctrans("g each") }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="line in liveLinesOf(level)" :key="line.id" class="border-t border-gray-100">
								<td class="px-3 py-1 text-gray-800">
									{{ line.label }}
									<span v-if="line.description" class="block text-gray-500">{{ line.description }}</span>
									<span v-if="!line.is_epr_reportable" class="block text-gray-400">{{ ctrans("Not reported for EPR") }}</span>
								</td>
								<td class="px-2 py-1 text-right tabular-nums">{{ formatNumber(line.quantity, 3) }}</td>
								<td class="px-3 py-1 text-right tabular-nums">
									{{ formatNumber(line.weight_g_each, 2) }}
									<FontAwesomeIcon v-if="line.weight_is_measured" :icon="faCheck" class="text-green-600" fixed-width :title="ctrans('Weighed by goods in')" />
								</td>
							</tr>
							<tr v-if="!liveLinesOf(level).length">
								<td colspan="3" class="px-3 py-2 text-gray-400">{{ ctrans("Nothing") }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Empty state -->
		<div v-if="!data.live && !editing" class="rounded border border-dashed border-gray-300 px-4 py-8 text-center text-gray-500">
			<FontAwesomeIcon :icon="faBoxOpen" class="mb-2 text-2xl text-gray-300" fixed-width aria-hidden="true" />
			<div>{{ ctrans("No packing spec yet: nothing tells the packers what goes in the carton.") }}</div>
			<Button v-if="data.store_route" class="mt-3" type="tertiary" :label="ctrans('Declare the packing')" @click="startEditing">
				<template #icon><FontAwesomeIcon :icon="faPlus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
			</Button>
		</div>

		<!-- Form -->
		<div v-if="editing" class="space-y-4">
			<div class="font-semibold text-gray-800">
				{{ data.live ? ctrans("New version of the packing of :code", { code: data.supplier_product.code }) : ctrans("Packing of :code", { code: data.supplier_product.code }) }}
			</div>

			<div class="grid gap-3 md:grid-cols-4">
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Packed by") }}</span>
					<Select v-model="form.packed_by" :options="packedByOptions" optionLabel="label" optionValue="value" :class="fieldFocusClass" fluid />
				</label>
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Effective from") }}</span>
					<DatePicker v-model="form.effective_from" dateFormat="d M yy" showIcon iconDisplay="input" :class="fieldFocusClass" fluid />
				</label>
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Carton length (cm)") }}</span>
					<InputNumber v-model="form.carton_length_cm" :min="0" :maxFractionDigits="1" :useGrouping="false" :class="fieldFocusClass" fluid />
				</label>
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Carton width (cm)") }}</span>
					<InputNumber v-model="form.carton_width_cm" :min="0" :maxFractionDigits="1" :useGrouping="false" :class="fieldFocusClass" fluid />
				</label>
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Carton height (cm)") }}</span>
					<InputNumber v-model="form.carton_height_cm" :min="0" :maxFractionDigits="1" :useGrouping="false" :class="fieldFocusClass" fluid />
				</label>
				<label class="space-y-1">
					<span class="block text-xs text-gray-500">{{ ctrans("Carton gross weight (g)") }}</span>
					<InputNumber v-model="form.declared_carton_gross_weight_g" :min="1" :class="fieldFocusClass" fluid />
				</label>
			</div>
			<div class="text-xs text-gray-500">
				{{ ctrans(":pack units per pack, :carton units per carton (:packs packs), from the supplier product", { pack: data.supplier_product.units_per_pack ?? 1, carton: unitsPerCarton, packs: formatNumber(packsPerCarton, 2) }) }}
			</div>

			<div class="grid gap-4 lg:grid-cols-3">
				<div v-for="level in levels" :key="level" class="space-y-2 rounded border border-gray-200 p-3">
					<div class="text-xs font-semibold text-gray-700">
						{{ data.options.levels[level] }}
						<InformationIcon :information="levelInformation[level]" class="ml-0.5 font-normal" />
					</div>
					<div v-for="{ line, index } in linesOf(level)" :key="index" class="space-y-1 rounded border border-gray-100 bg-gray-50 p-2">
						<div class="flex items-start gap-1">
							<Select
								v-model="line.packaging_component_id"
								:options="data.dropdown"
								optionLabel="label"
								optionValue="id"
								filter
								:placeholder="ctrans('Material')"
								:class="[fieldFocusClass, shakeClass(index, 'packaging_component_id')]"
								class="min-w-0 flex-1"
								@change="onMaterialChange(line)">
								<template #option="{ option }">
									<div class="text-xs">
										<div>{{ option.label }}</div>
										<div class="text-gray-400">{{ option.material }}<template v-if="!option.is_epr_reportable"> · {{ ctrans("not reported") }}</template></div>
									</div>
								</template>
							</Select>
							<Button type="tertiary" size="xs" :aria-label="ctrans('Remove')" @click="removeLine(index)">
								<template #icon><FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" /></template>
							</Button>
						</div>
						<div class="grid grid-cols-2 gap-1">
							<label>
								<span class="block text-[11px] text-gray-500">{{ ctrans("Quantity") }}</span>
								<InputNumber v-model="line.quantity" :min="0" :maxFractionDigits="3" :class="[fieldFocusClass, shakeClass(index, 'quantity')]" fluid size="small" />
							</label>
							<label>
								<span class="block text-[11px] text-gray-500">
									{{ ctrans("Grams each") }}
									<template v-if="line.packaging_component_id && !dropdownById[line.packaging_component_id]?.is_epr_reportable">({{ ctrans("optional") }})</template>
								</span>
								<InputNumber v-model="line.weight_g_each" :min="0" :maxFractionDigits="3" :class="[fieldFocusClass, shakeClass(index, 'weight_g_each')]" fluid size="small" :suffix="' ' + ctrans('grams')" />
							</label>
						</div>
						<Select v-model="line.role" :options="roleOptions" optionLabel="label" optionValue="value" filter :placeholder="ctrans('Role')" :class="fieldFocusClass" fluid size="small" />
						<InputText
							v-if="line.packaging_component_id && dropdownById[line.packaging_component_id]?.needs_review"
							v-model="line.description"
							:placeholder="ctrans('Describe the material')"
							:class="[fieldFocusClass, shakeClass(index, 'description')]"
							fluid
							size="small" />
						<div v-for="field in (['packaging_component_id', 'quantity', 'weight_g_each', 'description'] as const)" :key="field">
							<div v-if="lineFieldMissing(index, field)" class="text-xs text-red-600">{{ lineError(index, field) }}</div>
						</div>
					</div>
					<Button type="dashed" size="xs" full :label="ctrans('Add material')" @click="addLine(level)">
						<template #icon><FontAwesomeIcon :icon="faPlus" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</div>

			<div class="rounded border px-3 py-2 text-xs" :class="weightCheck?.warning ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600'">
				{{ ctrans("Packing material per carton: :grams g", { grams: formatNumber(packagingGrams) }) }}
				<template v-if="weightCheck">
					· {{ ctrans("with the net weight :expected g against the carton weight :carton g (:deviation%)", { expected: formatNumber(weightCheck.expected, 0), carton: formatNumber(weightCheck.cartonWeight, 0), deviation: (weightCheck.deviation > 0 ? "+" : "") + formatNumber(weightCheck.deviation) }) }}
					<template v-if="weightCheck.warning"> · {{ ctrans("check the weights: over ±:pct%", { pct: data.tolerance_pct }) }}</template>
				</template>
				<template v-else> · {{ ctrans("Enter the carton gross weight to check the packing against the goods") }}</template>
			</div>

			<div class="grid gap-3 md:grid-cols-2">
				<div class="space-y-1">
					<span class="block text-xs text-gray-500">
						<FontAwesomeIcon :icon="faCamera" fixed-width aria-hidden="true" />
						{{ data.live?.photo ? ctrans("Photo of an opened carton (keep the current one if empty)") : ctrans("Photo of an opened carton") }}
					</span>
					<FileUpload mode="basic" accept="image/*" :auto="false" customUpload :chooseLabel="form.photo ? form.photo.name : ctrans('Choose photo')" :pt="chooseFilePt" @select="(event: { files: File[] }) => (form.photo = event.files[0] ?? null)" />
					<div v-if="form.errors.photo" class="text-xs text-red-600">{{ form.errors.photo }}</div>
					<p class="text-[11px] text-gray-500">{{ ctrans("Open one packed carton and photograph it from above so every packing material can be seen. It shows what the list above describes, goes on the printed packing instruction, and is what goods in compares the carton with when it arrives.") }}</p>
				</div>
				<div v-if="data.copy_candidates.length" class="space-y-1">
					<span class="block text-xs text-gray-500">
						<FontAwesomeIcon :icon="faCopy" fixed-width aria-hidden="true" />
						{{ ctrans("Same packing for other products of this supplier") }}
					</span>
					<MultiSelect v-model="form.copy_to" :options="copyOptions" optionLabel="label" optionValue="id" filter display="chip" :maxSelectedLabels="5" :placeholder="ctrans('Choose products')" :class="fieldFocusClass" fluid />
					<ul class="list-disc space-y-0.5 pl-4 text-[11px] text-gray-500">
						<li>{{ ctrans("Optional. Saves time when other products of this supplier are packed exactly the same way, so you do not fill in the same form again for each one.") }}</li>
						<li>{{ ctrans("Each product you choose gets the same materials, quantities, weights and photo as a new version of its own packing spec.") }}</li>
						<li>{{ ctrans("Per unit and per pack quantities follow each product's own units per pack and per carton, so the grams per carton can differ.") }}</li>
						<li>{{ ctrans("Products marked ✓ already have a packing spec: it is replaced by this one, the old one stays in their versions.") }}</li>
						<li v-if="form.copy_to.length" class="font-medium text-gray-700">{{ ctrans("This will also save the packing of :count other products.", { count: form.copy_to.length }) }}</li>
					</ul>
				</div>
			</div>

			<div v-if="form.errors.lines" class="text-xs text-red-600">{{ form.errors.lines }}</div>

			<div class="flex gap-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click="editing = false">
					<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<div class="relative">
					<Button type="secondary" key="2" :label="ctrans('Save packing')" :loading="form.processing" @click="submit">
						<template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
					</Button>
					<PingIcon v-if="form.isDirty" class="text-[7px] text-red-500 !absolute top-0 right-0" v-tooltip="ctrans('Not saved yet')" aria-hidden="true" />
				</div>
			</div>
		</div>

		<!-- Versions -->
		<div v-if="data.versions.length > 1 || (data.versions.length && editing)" class="space-y-1">
			<div class="text-xs font-semibold text-gray-700">{{ ctrans("Versions") }}</div>
			<table class="w-full max-w-3xl text-xs">
				<thead class="whitespace-nowrap text-gray-500">
					<tr>
						<th class="py-1 pr-3 text-left font-normal">{{ ctrans("Version") }}</th>
						<th class="py-1 pr-3 text-left font-normal">{{ ctrans("Status") }}</th>
						<th class="py-1 pr-3 text-left font-normal">{{ ctrans("Effective from") }}</th>
						<th class="py-1 pr-3 text-left font-normal">{{ ctrans("Declared by") }}</th>
						<th class="py-1 pr-3 text-right font-normal">{{ ctrans("Packing g / carton") }}</th>
						<th class="py-1"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="version in data.versions" :key="version.id" class="border-t border-gray-100">
						<td class="py-1 pr-3">v{{ version.version }}</td>
						<td class="py-1 pr-3"><span class="rounded border px-1.5 py-0.5" :class="statusClass(version.status)">{{ version.status_label }}</span></td>
						<td class="py-1 pr-3">{{ formatDate(version.effective_from) }}</td>
						<td class="py-1 pr-3">{{ version.submitted_by ?? "—" }}</td>
						<td class="py-1 pr-3 text-right tabular-nums">{{ formatNumber(version.packaging_g) }}</td>
						<td class="py-1 text-right">
							<a :href="version.pdf_url" target="_blank" rel="noopener" class="secondaryLink">
								<FontAwesomeIcon :icon="faPrint" fixed-width :aria-label="ctrans('Print')" />
							</a>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<Dialog v-model:visible="returnDialog" modal :header="ctrans('Return the packing spec to the agent')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
			<div class="space-y-2 text-sm">
				<p class="text-gray-600">{{ ctrans("The goods stay where they are; only the declaration goes back. The agent sees the reason on this tab and in the supplier notes.") }}</p>
				<Textarea v-model="returnForm.reason" rows="4" autoResize fluid :class="fieldFocusClass" :placeholder="ctrans('What does not match?')" />
				<div v-if="returnForm.errors.reason" class="text-xs text-red-600">{{ returnForm.errors.reason }}</div>
			</div>
			<template #footer>
				<Button type="tertiary" :label="ctrans('Cancel')" @click="returnDialog = false">
					<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="tertiary" :label="ctrans('Return to agent')" :loading="returnForm.processing" @click="submitReturn">
					<template #icon><FontAwesomeIcon :icon="faUndo" class="text-amber-500" fixed-width aria-hidden="true" /></template>
				</Button>
			</template>
		</Dialog>
	</div>
</template>
