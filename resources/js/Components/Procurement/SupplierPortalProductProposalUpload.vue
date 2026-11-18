<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-18 09:40
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import axios from "axios"
import { router, usePage } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faDownload, faFileExcel, faPaperPlane, faTimes, faTrashAlt } from "@fal"

library.add(faCheckCircle, faDownload, faFileExcel, faPaperPlane, faTimes, faTrashAlt)

interface ProductProposalRoutes {
	template: string
	preview: string
	import: string
	max_rows: number
	products_per_request: number
	columns: { field: string; heading: string; required: boolean; note: string; example: string | number }[]
}

interface PreviewRow {
	row: number
	values: Record<string, any>
	errors: Record<string, string>
	files: File[]
	fileKey: number
	sent: boolean
}

const props = defineProps<{
	routes: ProductProposalRoutes
	maxFiles: number
	maxFileMb: number
}>()

const emit = defineEmits<{ (e: "previewing", isPreviewing: boolean): void }>()

const REQUIRED_FIELDS = ["name", "description", "proposed_price", "currency", "unit_basis"]
const MAX_BYTES_PER_REQUEST = 80 * 1024 * 1024

const locale = useLocaleStore()
const group = computed(() => String(usePage().props.group ?? ""))

const rows = ref<PreviewRow[]>([])
const problems = ref<string[]>([])
const isReading = ref(false)
const isSending = ref(false)
const sentCount = ref(0)
const finishedCount = ref<number | null>(null)
const spreadsheetKey = ref(0)

const pendingRows = computed(() => rows.value.filter((row) => !row.sent))
const rowsWithErrors = computed(() => pendingRows.value.filter((row) => Object.keys(row.errors).length))
const totalToSend = ref(0)

const messagesOf = (error: any): string[] => {
	if (error?.response?.status === 429) {
		return [ctrans("You sent a lot in a short time. Wait a while and send the rest again; what was sent stays sent.")]
	}
	const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
	if (errors) {
		return Object.values(errors).flat()
	}
	return [ctrans("Something went wrong. Please try again.")]
}

const setPreviewing = () => emit("previewing", rows.value.length > 0)

const readSpreadsheet = async (file: File | undefined) => {
	if (!file) {
		return
	}
	isReading.value = true
	problems.value = []
	finishedCount.value = null
	const body = new FormData()
	body.append("file", file)
	try {
		const { data } = await axios.post<{ rows: { row: number; values: Record<string, any>; errors: Record<string, string> }[] }>(props.routes.preview, body)
		rows.value = data.rows.map((row) => ({
			...row,
			values: { ...row.values, proposed_price: row.values.proposed_price === null ? null : Number(row.values.proposed_price), lead_time_days: row.values.lead_time_days === null ? null : Number(row.values.lead_time_days) },
			errors: Array.isArray(row.errors) ? {} : row.errors,
			files: [],
			fileKey: 0,
			sent: false,
		}))
	} catch (error) {
		problems.value = messagesOf(error)
	} finally {
		isReading.value = false
		spreadsheetKey.value++
		setPreviewing()
	}
}

const changed = (row: PreviewRow, field: string, value: unknown) => {
	row.values[field] = value === "" ? null : value
	delete row.errors[field]
}

const typedNumber = (value: string | number | undefined) => (typeof value === "number" ? value : null)

const choosePictures = (row: PreviewRow, files: File[]) => {
	const tooBig = files.filter((file) => file.size > props.maxFileMb * 1024 * 1024)
	row.files = [...row.files, ...files.filter((file) => !tooBig.includes(file))].slice(0, props.maxFiles)
	row.fileKey++
	if (tooBig.length) {
		row.errors.files = ctrans("Larger than :mb MB: :names", { mb: String(props.maxFileMb), names: tooBig.map((file) => file.name).join(", ") })
	} else {
		delete row.errors.files
	}
}

const thumbnails = new Map<File, string>()
const thumbnailOf = (file: File) => {
	if (!file.type.startsWith("image/")) {
		return null
	}
	if (!thumbnails.has(file)) {
		thumbnails.set(file, URL.createObjectURL(file))
	}
	return thumbnails.get(file)
}
const removePicture = (row: PreviewRow, file: File) => {
	row.files = row.files.filter((chosen) => chosen !== file)
	const url = thumbnails.get(file)
	if (url) {
		URL.revokeObjectURL(url)
		thumbnails.delete(file)
	}
}
onBeforeUnmount(() => thumbnails.forEach((url) => URL.revokeObjectURL(url)))

const removeRow = (row: PreviewRow) => {
	row.files.forEach((file) => removePicture(row, file))
	rows.value = rows.value.filter((candidate) => candidate !== row)
	setPreviewing()
}

const cancel = () => {
	rows.value.forEach((row) => row.files.forEach((file) => removePicture(row, file)))
	rows.value = []
	problems.value = []
	setPreviewing()
}

const checkRequired = () => {
	for (const row of pendingRows.value) {
		for (const field of REQUIRED_FIELDS) {
			if (row.values[field] === null || row.values[field] === undefined || row.values[field] === "") {
				row.errors[field] = ctrans("Required")
			}
		}
	}
	return rowsWithErrors.value.length === 0
}

const batches = (pending: PreviewRow[]) => {
	const result: PreviewRow[][] = []
	let batch: PreviewRow[] = []
	let bytes = 0
	for (const row of pending) {
		const rowBytes = row.files.reduce((total, file) => total + file.size, 0)
		if (batch.length && (batch.length >= props.routes.products_per_request || bytes + rowBytes > MAX_BYTES_PER_REQUEST)) {
			result.push(batch)
			batch = []
			bytes = 0
		}
		batch.push(row)
		bytes += rowBytes
	}
	if (batch.length) {
		result.push(batch)
	}
	return result
}

const bodyOf = (batch: PreviewRow[]) => {
	const body = new FormData()
	batch.forEach((row, index) => {
		body.append(`products[${index}][row]`, String(row.row))
		for (const [field, value] of Object.entries(row.values)) {
			if (value !== null && value !== undefined && value !== "") {
				body.append(`products[${index}][${field}]`, String(value))
			}
		}
		row.files.forEach((file) => body.append(`products[${index}][files][]`, file))
	})
	return body
}

const send = async () => {
	problems.value = []
	if (!checkRequired()) {
		problems.value = [ctrans("Correct the rows marked in red first.")]
		return
	}
	isSending.value = true
	sentCount.value = 0
	totalToSend.value = pendingRows.value.length
	try {
		for (const batch of batches(pendingRows.value)) {
			try {
				await axios.post(props.routes.import, bodyOf(batch))
				batch.forEach((row) => (row.sent = true))
				sentCount.value += batch.length
			} catch (error: any) {
				const errors = error?.response?.status === 422 ? (error.response.data.errors as Record<string, string[]>) : null
				const general: string[] = []
				for (const [key, messages] of Object.entries(errors ?? {})) {
					const match = key.match(/^products\.(\d+)\.([a-z_]+)/)
					if (match && batch[Number(match[1])]) {
						batch[Number(match[1])].errors[match[2]] = messages[0]
					} else {
						general.push(...messages)
					}
				}
				problems.value = errors ? (general.length ? general : [ctrans("Correct the rows marked in red, then send again. What was sent stays sent.")]) : messagesOf(error)
				return
			}
		}
		finishedCount.value = rows.value.length
		cancel()
	} finally {
		isSending.value = false
		if (sentCount.value) {
			router.reload({ only: ["details"] })
		}
	}
}

const fieldLabels = computed<Record<string, string>>(() => ({
	name: ctrans("Product name"),
	description: ctrans("Description"),
	proposed_price: ctrans("Proposed price"),
	currency: ctrans("Currency"),
	unit_basis: ctrans("Price is per"),
	moq: ctrans("Minimum order"),
	lead_time_days: ctrans("Lead time, days"),
	dimensions: ctrans("Dimensions"),
	materials: ctrans("Materials"),
	packaging: ctrans("Packaging"),
	files: ctrans("Pictures and documents"),
}))

const label = "flex flex-col gap-1 text-xs"
const caption = "font-medium text-gray-700"
</script>

<template>
	<div class="space-y-3 rounded border border-gray-200 bg-gray-50 p-3 text-sm">
		<div class="flex items-center gap-2 font-medium text-gray-700">
			<FontAwesomeIcon icon="fal fa-file-excel" class="text-green-600" fixed-width aria-hidden="true" />
			{{ ctrans("Many products in Excel?") }}
		</div>

		<template v-if="!rows.length">
			<p class="text-xs text-gray-500">
				{{ ctrans("Download our template and fill one product per row (up to :count). Upload it to check every product and add its pictures before you send them.", { count: String(routes.max_rows) }) }}
			</p>
			<div class="overflow-x-auto rounded border border-gray-200 bg-white">
				<table class="min-w-full divide-y divide-gray-100 text-xs">
					<thead class="bg-gray-50 text-left text-gray-600">
						<tr>
							<th class="px-3 py-2 font-medium">{{ ctrans("Column") }}</th>
							<th class="px-3 py-2 font-medium">{{ ctrans("What to write") }}</th>
							<th class="px-3 py-2 font-medium">{{ ctrans("Example") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="column in routes.columns" :key="column.field" class="align-top">
							<td class="whitespace-nowrap px-3 py-2 font-medium text-gray-800">{{ column.heading }} <span v-if="column.required" class="text-red-500">*</span></td>
							<td class="px-3 py-2 text-gray-600">{{ column.note }}</td>
							<td class="px-3 py-2 italic text-gray-500">{{ column.example }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="flex flex-wrap items-center gap-2">
				<a :href="routes.template" download>
					<Button type="tertiary" size="xs" :label="ctrans('Download template')">
						<template #icon><FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" /></template>
					</Button>
				</a>
				<FileUpload :key="spreadsheetKey" mode="basic" :auto="false" customUpload accept=".xlsx,.xls,.csv" :disabled="isReading" :chooseLabel="isReading ? ctrans('Reading…') : ctrans('Upload filled file')" @select="(event) => readSpreadsheet(event.files[0])" />
			</div>
			<p v-if="finishedCount" class="rounded border border-green-200 bg-green-50 p-2 text-xs text-green-800">
				{{ ctrans("Thank you, we received :count products. You can follow them in the list below.", { count: String(finishedCount) }) }}
			</p>
		</template>

		<template v-else>
			<p class="text-xs text-gray-500">
				{{ ctrans("Check each product, correct what is marked in red and add its pictures. Nothing is sent until you press Send.") }}
			</p>

			<ul class="space-y-2">
				<li v-for="row in rows" :key="row.row" class="rounded border bg-white p-3" :class="row.sent ? 'border-green-200' : Object.keys(row.errors).length ? 'border-red-300' : 'border-gray-200'">
					<div class="mb-2 flex items-center justify-between gap-2">
						<span class="text-xs font-semibold text-gray-500">
							{{ ctrans("Row :row", { row: String(row.row) }) }}
							<span v-if="row.sent" class="ml-2 text-green-700"><FontAwesomeIcon icon="fal fa-check-circle" fixed-width aria-hidden="true" /> {{ ctrans("Sent") }}</span>
						</span>
						<Button v-if="!row.sent" type="tertiary" size="xxs" :label="ctrans('Remove')" :disabled="isSending" @click="removeRow(row)">
							<template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>

					<p v-if="row.sent" class="text-sm text-gray-700">{{ row.values.name }}</p>

					<div v-else class="grid gap-2 md:grid-cols-4">
						<label :class="[label, 'md:col-span-2']">
							<span :class="caption">{{ fieldLabels.name }} <span class="text-red-500">*</span></span>
							<InputText :modelValue="row.values.name" size="small" :invalid="!!row.errors.name" @update:modelValue="(value) => changed(row, 'name', value)" />
							<span v-if="row.errors.name" class="text-red-600">{{ row.errors.name }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.proposed_price }} <span class="text-red-500">*</span></span>
							<InputNumber :modelValue="row.values.proposed_price" size="small" v-bind="locale.unitCostInputOptions(row.values.currency)" :invalid="!!row.errors.proposed_price" @input="(event) => changed(row, 'proposed_price', typedNumber(event.value))" />
							<span v-if="row.errors.proposed_price" class="text-red-600">{{ row.errors.proposed_price }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.currency }} <span class="text-red-500">*</span></span>
							<InputText :modelValue="row.values.currency" size="small" maxlength="3" class="uppercase" :invalid="!!row.errors.currency" @update:modelValue="(value) => changed(row, 'currency', value?.toUpperCase())" />
							<span v-if="row.errors.currency" class="text-red-600">{{ row.errors.currency }}</span>
						</label>
						<label :class="[label, 'md:col-span-4']">
							<span :class="caption">{{ fieldLabels.description }} <span class="text-red-500">*</span></span>
							<Textarea :modelValue="row.values.description" rows="2" autoResize :invalid="!!row.errors.description" @update:modelValue="(value) => changed(row, 'description', value)" />
							<span v-if="row.errors.description" class="text-red-600">{{ row.errors.description }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.unit_basis }} <span class="text-red-500">*</span></span>
							<InputText :modelValue="row.values.unit_basis" size="small" :placeholder="ctrans('e.g. piece, pack of 6')" :invalid="!!row.errors.unit_basis" @update:modelValue="(value) => changed(row, 'unit_basis', value)" />
							<span v-if="row.errors.unit_basis" class="text-red-600">{{ row.errors.unit_basis }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.moq }}</span>
							<InputText :modelValue="row.values.moq" size="small" :invalid="!!row.errors.moq" @update:modelValue="(value) => changed(row, 'moq', value)" />
							<span v-if="row.errors.moq" class="text-red-600">{{ row.errors.moq }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.lead_time_days }}</span>
							<InputNumber :modelValue="row.values.lead_time_days" size="small" :min="0" :invalid="!!row.errors.lead_time_days" @input="(event) => changed(row, 'lead_time_days', typedNumber(event.value))" />
							<span v-if="row.errors.lead_time_days" class="text-red-600">{{ row.errors.lead_time_days }}</span>
						</label>
						<label :class="label">
							<span :class="caption">{{ fieldLabels.dimensions }}</span>
							<InputText :modelValue="row.values.dimensions" size="small" :invalid="!!row.errors.dimensions" @update:modelValue="(value) => changed(row, 'dimensions', value)" />
							<span v-if="row.errors.dimensions" class="text-red-600">{{ row.errors.dimensions }}</span>
						</label>
						<label :class="[label, 'md:col-span-2']">
							<span :class="caption">{{ fieldLabels.materials }}</span>
							<Textarea :modelValue="row.values.materials" rows="1" autoResize :invalid="!!row.errors.materials" @update:modelValue="(value) => changed(row, 'materials', value)" />
							<span v-if="row.errors.materials" class="text-red-600">{{ row.errors.materials }}</span>
						</label>
						<label :class="[label, 'md:col-span-2']">
							<span :class="caption">{{ fieldLabels.packaging }}</span>
							<Textarea :modelValue="row.values.packaging" rows="1" autoResize :invalid="!!row.errors.packaging" @update:modelValue="(value) => changed(row, 'packaging', value)" />
							<span v-if="row.errors.packaging" class="text-red-600">{{ row.errors.packaging }}</span>
						</label>

						<div class="flex flex-col gap-1 text-xs md:col-span-4">
							<span :class="caption">{{ fieldLabels.files }}</span>
							<div class="flex flex-wrap items-center gap-2">
								<div v-for="file in row.files" :key="file.name + file.size" class="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50">
									<img v-if="thumbnailOf(file)" :src="thumbnailOf(file)!" :alt="file.name" class="h-full w-full object-cover" />
									<span v-else class="break-all p-1 text-center text-[10px] text-gray-500">{{ file.name }}</span>
									<button type="button" class="absolute right-0 top-0 rounded-bl bg-white/90 px-1 text-red-500" :aria-label="ctrans('Remove')" @click="removePicture(row, file)">
										<FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
									</button>
								</div>
								<FileUpload v-if="row.files.length < maxFiles" :key="row.fileKey" mode="basic" :auto="false" customUpload multiple accept="image/jpeg,image/png,image/webp,application/pdf" :chooseLabel="ctrans('Add pictures')" @select="(event) => choosePictures(row, event.files)" />
							</div>
							<span v-if="row.errors.files" class="text-red-600">{{ row.errors.files }}</span>
						</div>
					</div>
				</li>
			</ul>

			<div v-if="problems.length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
				<p v-for="problem in problems" :key="problem">{{ problem }}</p>
			</div>
			<progress v-if="isSending" :value="sentCount" :max="totalToSend" class="w-full" />

			<div class="flex flex-wrap items-center justify-end gap-2">
				<span v-if="isSending" class="text-xs text-gray-500">{{ ctrans("Sending :sent of :total…", { sent: String(sentCount), total: String(totalToSend) }) }}</span>
				<span v-else-if="rowsWithErrors.length" class="text-xs text-red-600">{{ ctrans(":count products to correct", { count: String(rowsWithErrors.length) }) }}</span>
				<Button type="tertiary" size="xs" :label="ctrans('Cancel')" :disabled="isSending" @click="cancel">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" size="xs" :label="ctrans('Send :count products to :group', { count: String(pendingRows.length), group })" :disabled="!pendingRows.length" :loading="isSending" @click="send">
					<template #icon><FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>

		<div v-if="!rows.length && problems.length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
			<p v-for="problem in problems" :key="problem">{{ problem }}</p>
		</div>
	</div>
</template>
