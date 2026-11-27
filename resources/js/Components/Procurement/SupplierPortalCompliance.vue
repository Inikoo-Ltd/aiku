<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-24 11:36
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Checkbox from "primevue/checkbox"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faClock, faExclamationTriangle, faTimesCircle, faMinus, faDownload, faUpload, faTimes, faSearch } from "@fal"

export type ComplianceDocumentStatus = "on_file" | "not_sent" | "waiting" | "expired" | "expiring" | "more_information" | "not_accepted"

export type SupplierComplianceDocument = {
	scope: string
	label: string
	status: ComplianceDocumentStatus
	expires_on: string | null
	feedback: string | null
	submission_id: number | null
	my_files: { submission_id: number; name: string; ulid: string; state: string; sent_at: string | null }[]
}

export type SupplierComplianceProduct = {
	id: number
	code: string
	name: string | null
	has_trade_unit: boolean
	open_orders: { id: number; reference: string }[]
	in_plan: boolean
	documents: SupplierComplianceDocument[]
	needs_attention: boolean
}

const props = defineProps<{
	products: SupplierComplianceProduct[]
	submitUrl?: string | null
	fileRoute?: string | null
	purchaseOrderId?: number | null
	compact?: boolean
}>()

const emit = defineEmits<{
	sent: []
}>()

const statusStyles: Record<ComplianceDocumentStatus, { icon: object; class: string; label: string }> = {
	on_file: { icon: faCheckCircle, class: "text-green-600", label: ctrans("On file") },
	not_sent: { icon: faMinus, class: "text-gray-300", label: ctrans("Not sent") },
	waiting: { icon: faClock, class: "text-sky-600", label: ctrans("Waiting for our review") },
	expiring: { icon: faExclamationTriangle, class: "text-amber-500", label: ctrans("Expires soon") },
	expired: { icon: faTimesCircle, class: "text-red-600", label: ctrans("Expired") },
	more_information: { icon: faExclamationTriangle, class: "text-amber-500", label: ctrans("More information needed") },
	not_accepted: { icon: faTimesCircle, class: "text-red-600", label: ctrans("Not accepted, send it again") },
}

const documentTypes = computed(() => props.products[0]?.documents.map((document) => ({ scope: document.scope, label: document.label })) ?? [])

const search = ref("")
const attentionOnly = ref(false)
const shownProducts = computed(() => {
	const term = search.value.trim().toLowerCase()
	return props.products.filter((product) => (!attentionOnly.value || product.needs_attention) && (!term || `${product.code} ${product.name ?? ""}`.toLowerCase().includes(term)))
})
const attentionCount = computed(() => props.products.filter((product) => product.needs_attention).length)

const openedKey = ref<{ productId: number; scope: string } | null>(null)
const opened = computed(() => {
	const product = props.products.find((candidate) => candidate.id === openedKey.value?.productId)
	const document = product?.documents.find((candidate) => candidate.scope === openedKey.value?.scope)
	return product && document ? { product, document } : null
})
const open = (product: SupplierComplianceProduct, document: SupplierComplianceDocument) => {
	openedKey.value = { productId: product.id, scope: document.scope }
	resetUpload()
}

const fileUrl = (submissionId: number, ulid: string): string | null =>
	props.fileRoute ? props.fileRoute.replace("/submissions/0/", `/submissions/${submissionId}/`).replace("/files/MEDIA", `/files/${ulid}`) : null

const expiresOn = ref<Date | null>(null)
const note = ref("")
const files = ref<File[]>([])
const fileKey = ref(0)
const isSending = ref(false)
const errors = ref<string[]>([])
const sentMessage = ref<string | null>(null)

const resetUpload = () => {
	expiresOn.value = null
	note.value = ""
	files.value = []
	fileKey.value++
	errors.value = []
	sentMessage.value = null
}

const isoDate = (date: Date | null) => (date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null)

const canUpload = computed(() => !!props.submitUrl && !!opened.value?.product.has_trade_unit)

async function send() {
	if (!opened.value || !props.submitUrl) {
		return
	}
	isSending.value = true
	errors.value = []
	const body = new FormData()
	body.append("document_type", opened.value.document.scope)
	body.append("supplier_product_id", String(opened.value.product.id))
	if (props.purchaseOrderId) {
		body.append("purchase_order_id", String(props.purchaseOrderId))
	}
	if (expiresOn.value) {
		body.append("expires_on", isoDate(expiresOn.value) as string)
	}
	if (note.value.trim()) {
		body.append("note", note.value.trim())
	}
	files.value.forEach((file) => body.append("files[]", file))
	try {
		await axios.post(props.submitUrl, body, { headers: { Accept: "application/json" } })
		sentMessage.value = ctrans("Sent. AW Group reviews it and you can follow it here and in Grow together.")
		files.value = []
		fileKey.value++
		emit("sent")
	} catch (error: any) {
		const fieldErrors = error?.response?.data?.errors
		errors.value = fieldErrors ? (Object.values(fieldErrors).flat() as string[]) : [error?.response?.data?.message ?? ctrans("Something went wrong")]
	} finally {
		isSending.value = false
	}
}
</script>

<template>
	<div class="space-y-3">
		<div v-if="!compact" class="flex flex-wrap items-center gap-3">
			<IconField class="w-64">
				<InputIcon><FontAwesomeIcon :icon="faSearch" fixed-width aria-hidden="true" /></InputIcon>
				<InputText v-model="search" :placeholder="ctrans('Search products')" size="small" class="w-full" />
			</IconField>
			<label class="flex items-center gap-2 text-sm text-gray-700">
				<Checkbox v-model="attentionOnly" binary inputId="compliance-attention-only" />
				{{ ctrans("Needs your attention only") }} ({{ attentionCount }})
			</label>
		</div>

		<div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
			<span v-for="(style, status) in statusStyles" :key="status" class="flex items-center gap-1">
				<FontAwesomeIcon :icon="style.icon" :class="style.class" fixed-width aria-hidden="true" />{{ style.label }}
			</span>
		</div>

		<p v-if="!products.length" class="py-6 text-center text-sm text-gray-400">{{ ctrans("No products on open orders or in the plan for next orders") }}</p>
		<p v-else-if="!shownProducts.length" class="py-6 text-center text-sm text-gray-400">{{ ctrans("Nothing matches") }}</p>

		<div v-else class="overflow-x-auto rounded-lg border border-gray-200">
			<table class="w-full text-sm">
				<thead class="whitespace-nowrap bg-gray-50 text-xs text-gray-500">
					<tr>
						<th class="sticky left-0 bg-gray-50 px-3 py-2 text-left font-medium">{{ ctrans("Product") }}</th>
						<th v-for="type in documentTypes" :key="type.scope" class="px-2 py-2 text-center font-medium">{{ type.label }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="product in shownProducts" :key="product.id" :class="product.needs_attention ? 'bg-amber-50/40' : ''">
						<td class="sticky left-0 bg-white px-3 py-2 align-top">
							<div class="whitespace-nowrap font-medium text-gray-900">{{ product.code }}</div>
							<div v-if="product.name" class="max-w-xs text-xs text-gray-500">{{ product.name }}</div>
							<div v-if="!compact && (product.open_orders.length || product.in_plan)" class="mt-1 flex flex-wrap gap-1">
								<span v-for="order in product.open_orders" :key="order.id" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ order.reference }}</span>
								<span v-if="product.in_plan" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600">{{ ctrans("In the plan") }}</span>
							</div>
						</td>
						<td v-for="document in product.documents" :key="document.scope" class="px-2 py-2 text-center align-top">
							<button
								type="button"
								class="rounded border border-dashed border-gray-300 px-2 py-1 transition-colors hover:border-solid hover:border-[var(--app-accent)] hover:bg-[var(--app-accent-soft)] focus-visible:border-solid focus-visible:border-[var(--app-accent)] focus-visible:outline-none"
								v-tooltip="`${document.label}: ${statusStyles[document.status].label}${document.expires_on ? ` · ${useFormatTime(document.expires_on)}` : ''}`"
								@click="open(product, document)"
							>
								<FontAwesomeIcon :icon="statusStyles[document.status].icon" :class="statusStyles[document.status].class" fixed-width aria-hidden="true" />
								<span class="sr-only">{{ document.label }}: {{ statusStyles[document.status].label }}</span>
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<Dialog
			:visible="!!opened"
			modal
			:header="opened ? `${opened.document.label} · ${opened.product.code}` : ''"
			:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible: boolean) => !visible && (openedKey = null)"
		>
			<div v-if="opened" class="space-y-3 text-sm">
				<p class="flex items-center gap-1.5" :class="statusStyles[opened.document.status].class">
					<FontAwesomeIcon :icon="statusStyles[opened.document.status].icon" fixed-width aria-hidden="true" />
					<span class="font-medium">{{ statusStyles[opened.document.status].label }}</span>
					<span v-if="opened.document.expires_on" class="text-xs text-gray-500">· {{ ctrans("expires :date", { date: useFormatTime(opened.document.expires_on) }) }}</span>
				</p>
				<p v-if="opened.document.feedback && ['more_information', 'not_accepted'].includes(opened.document.status)" class="whitespace-pre-line rounded bg-amber-50 p-2 text-amber-900">
					{{ opened.document.feedback }}
				</p>
				<p class="text-xs text-gray-500">{{ ctrans("Every document is optional for now. Send it when you have it: it helps us clear your goods without delay.") }}</p>

				<div v-if="opened.document.my_files.length">
					<p class="mb-1 text-xs font-medium text-gray-500">{{ ctrans("What you sent") }}</p>
					<ul class="divide-y divide-gray-100 rounded-md border border-gray-200">
						<li v-for="file in opened.document.my_files" :key="`${file.submission_id}-${file.ulid}`" class="flex items-center gap-2 px-3 py-1.5">
							<span class="min-w-0 flex-1 truncate text-gray-700">{{ file.name }}</span>
							<span v-if="file.sent_at" class="whitespace-nowrap text-xs text-gray-400">{{ useFormatTime(file.sent_at) }}</span>
							<a v-if="fileUrl(file.submission_id, file.ulid)" :href="fileUrl(file.submission_id, file.ulid) as string" class="text-gray-400 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')">
								<FontAwesomeIcon :icon="faDownload" fixed-width aria-hidden="true" />
							</a>
						</li>
					</ul>
				</div>

				<template v-if="canUpload">
					<div class="space-y-2 border-t border-gray-100 pt-3">
						<p class="font-medium text-gray-700">{{ ctrans("Send a new file") }}</p>
						<label class="flex flex-col gap-1">
							<span class="text-xs font-medium text-gray-600">{{ ctrans("Files") }} <span class="text-red-500">*</span></span>
							<FileUpload :key="fileKey" mode="basic" :auto="false" customUpload multiple accept=".pdf,image/jpeg,image/png" :chooseLabel="ctrans('Choose files')" @select="(event) => (files = event.files)" />
						</label>
						<label class="flex flex-col gap-1">
							<span class="text-xs font-medium text-gray-600">{{ ctrans("Expires on") }}</span>
							<DatePicker v-model="expiresOn" dateFormat="dd M yy" showIcon :minDate="new Date()" />
						</label>
						<label class="flex flex-col gap-1">
							<span class="text-xs font-medium text-gray-600">{{ ctrans("Note") }}</span>
							<Textarea v-model="note" rows="2" autoResize />
						</label>
						<p v-for="(error, index) in errors" :key="index" class="text-xs text-red-600">{{ error }}</p>
						<p v-if="sentMessage" class="text-xs text-green-700">{{ sentMessage }}</p>
					</div>
				</template>
				<p v-else-if="!opened.product.has_trade_unit" class="text-xs text-gray-500">{{ ctrans("This product is not set up for documents yet. Ask your contact at AW Group.") }}</p>
			</div>
			<template v-if="opened && canUpload" #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Close')" @click="openedKey = null">
						<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button type="primary" full :label="ctrans('Send to AW Group')" :loading="isSending" :disabled="!files.length" @click="send">
						<template #icon><FontAwesomeIcon :icon="faUpload" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</template>
		</Dialog>
	</div>
</template>
