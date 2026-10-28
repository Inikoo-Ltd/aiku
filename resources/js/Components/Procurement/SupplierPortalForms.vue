<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { useForm, usePage } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import InputNumber from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Select from "primevue/select"
import DatePicker from "primevue/datepicker"
import Checkbox from "primevue/checkbox"
import FileUpload from "primevue/fileupload"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faSave, faTimes } from "@fal"

library.add(faPaperPlane, faSave, faTimes)

export interface PortalForms {
	submit: Record<string, string>
	update: string
	document_types: { value: string; label: string }[]
	max_files: number
	max_file_mb: number
}

export interface EditedSubmission {
	id: number
	type: string
	state: string
	data: Record<string, any> | null
	feedback: string | null
}

const props = defineProps<{
	forms: PortalForms
	products: { id: number; code: string; name: string; price?: number }[]
	currency: string | null
	editing: EditedSubmission | null
	initialType?: string
	lockedType?: string
}>()

const emit = defineEmits<{ (e: "done"): void }>()

const types = computed(() => [
	{ value: "referral", label: ctrans("Refer a customer") },
	{ value: "photos", label: ctrans("Improve pictures") },
	{ value: "product_proposal", label: ctrans("Suggest a product") },
	{ value: "company_details", label: ctrans("Company details") },
	{ value: "compliance_document", label: ctrans("Compliance document") },
])
const pictureKinds = computed(() => [
	{ value: "product", label: ctrans("Product") },
	{ value: "detail", label: ctrans("Detail") },
	{ value: "packaging", label: ctrans("Packaging") },
	{ value: "lifestyle", label: ctrans("Lifestyle") },
])

const type = ref(props.lockedType ?? props.initialType ?? "referral")
const fileKey = ref(0)

const blank = () => ({
	supplier_product_id: null as number | null,
	picture_kind: "product",
	note: "",
	name: "",
	contact_name: "",
	email: "",
	phone: "",
	contact_website: "",
	address: "",
	company_name: "",
	country: "",
	products_of_interest: "",
	permission_confirmed: false,
	description: "",
	proposed_price: null as number | null,
	currency: props.currency ?? "",
	unit_basis: "",
	moq: "",
	lead_time_days: null as number | null,
	materials: "",
	dimensions: "",
	packaging: "",
	document_type: null as string | null,
	expires_on: null as Date | null,
	effective_from: null as Date | null,
	reason: "",
	draft: false,
	files: [] as File[],
})

const form = useForm(blank())

watch(
	() => props.editing,
	(editing) => {
		form.defaults(blank())
		form.reset()
		if (!editing && props.lockedType === "price_change" && props.products.length === 1) {
			form.supplier_product_id = props.products[0].id
		}
		if (editing) {
			type.value = editing.type
			Object.assign(form, editing.data ?? {})
			for (const field of ["expires_on", "effective_from"] as const) {
				if (typeof form[field] === "string") {
					form[field] = new Date(form[field] as unknown as string)
				}
			}
		}
	},
	{ immediate: true }
)

const productOptions = computed(() => props.products.map((product) => ({ value: product.id, label: `${product.code} · ${product.name}` })))
const locale = useLocaleStore()
const currentPrice = computed(() => props.products.find((product) => product.id === form.supplier_product_id)?.price ?? null)
const fieldsFor: Record<string, string[]> = {
	company_details: ["name", "contact_name", "phone", "contact_website", "address", "note"],
	photos: ["supplier_product_id", "picture_kind", "note", "files"],
	referral: ["company_name", "country", "contact_website", "contact_name", "email", "phone", "products_of_interest", "note", "permission_confirmed"],
	product_proposal: ["name", "description", "proposed_price", "currency", "unit_basis", "moq", "lead_time_days", "materials", "dimensions", "packaging", "draft", "files"],
	compliance_document: ["document_type", "supplier_product_id", "expires_on", "note", "files"],
	price_change: ["supplier_product_id", "proposed_price", "effective_from", "reason"],
}

const isoDate = (date: Date | null) => (date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null)

const send = (asDraft = false) => {
	form.draft = asDraft
	const url = props.editing ? props.forms.update.replace("/submissions/0/", `/submissions/${props.editing.id}/`) : props.forms.submit[type.value]
	form
		.transform((data) => {
			const picked: Record<string, any> = {}
			for (const field of fieldsFor[type.value]) {
				const value = (data as Record<string, any>)[field]
				picked[field] = value instanceof Date ? isoDate(value) : typeof value === "boolean" ? (value ? 1 : 0) : value
			}
			return picked
		})
		.post(url, {
			forceFormData: true,
			preserveScroll: true,
			onSuccess: () => {
				form.reset()
				fileKey.value++
				emit("done")
			},
		})
}

const label = "flex flex-col gap-1 text-sm"
const caption = "font-medium text-gray-700"
</script>

<template>
	<div class="space-y-3">
		<div v-if="editing" class="flex flex-wrap items-center justify-between gap-2 rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
			<span>
				{{
					editing.state === "draft"
						? ctrans("You are completing your draft.")
						: editing.state === "more_information_requested"
							? ctrans("We asked for more information: :feedback", { feedback: editing.feedback ?? "" })
							: ctrans("You are editing your proposed price change. Your contacts are notified again when you send it.")
				}}
			</span>
			<Button type="tertiary" size="xxs" :label="ctrans('Cancel')" @click="emit('done')">
				<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
			</Button>
		</div>
		<template v-else-if="!lockedType">
			<div class="md:hidden">
				<Select v-model="type" :options="types" optionLabel="label" optionValue="value" class="w-full" :aria-label="ctrans('What to send')" />
			</div>
			<div class="hidden md:block">
				<SegmentedToggle v-model="type" :options="types" :aria-label="ctrans('What to send')" />
			</div>
		</template>

		<form class="grid gap-3 md:grid-cols-2" @submit.prevent="send(false)">
			<template v-if="type === 'referral'">
				<p class="text-xs text-gray-500 md:col-span-2">{{ ctrans("Small shops that cannot reach your minimum order can buy your products from us. Tell us who they are and we will contact them.") }}</p>
				<label :class="label"><span :class="caption">{{ ctrans("Business name") }} *</span><InputText v-model="form.company_name" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Country") }} *</span><InputText v-model="form.country" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Contact person") }}</span><InputText v-model="form.contact_name" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Website") }}</span><InputText v-model="form.contact_website" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Email") }}</span><InputText v-model="form.email" type="email" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Phone") }}</span><InputText v-model="form.phone" /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Products they are interested in") }}</span><Textarea v-model="form.products_of_interest" rows="2" autoResize /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Anything useful to know") }}</span><Textarea v-model="form.note" rows="2" autoResize /></label>
				<label class="flex items-center gap-2 text-sm md:col-span-2"><Checkbox v-model="form.permission_confirmed" binary />{{ ctrans("This business agreed that I share their details with :group", { group: String(usePage().props.group ?? "") }) }}</label>
			</template>

			<template v-else-if="type === 'photos'">
				<label :class="label"><span :class="caption">{{ ctrans("Product") }} *</span><Select v-model="form.supplier_product_id" :options="productOptions" optionLabel="label" optionValue="value" filter /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Kind of picture") }}</span><Select v-model="form.picture_kind" :options="pictureKinds" optionLabel="label" optionValue="value" /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Note") }}</span><Textarea v-model="form.note" rows="2" autoResize /></label>
			</template>

			<template v-else-if="type === 'product_proposal'">
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Product name") }} *</span><InputText v-model="form.name" /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Description") }} *</span><Textarea v-model="form.description" rows="3" autoResize /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Proposed price") }} *</span><InputNumber v-model="form.proposed_price" v-bind="locale.unitCostInputOptions(form.currency)" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Currency") }} *</span><InputText v-model="form.currency" maxlength="3" class="uppercase" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Price is per") }} *</span><InputText v-model="form.unit_basis" :placeholder="ctrans('e.g. piece, pack of 6')" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Minimum order") }}</span><InputText v-model="form.moq" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Lead time, days") }}</span><InputNumber v-model="form.lead_time_days" :min="0" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Dimensions") }}</span><InputText v-model="form.dimensions" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Materials") }}</span><Textarea v-model="form.materials" rows="2" autoResize /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Packaging") }}</span><Textarea v-model="form.packaging" rows="2" autoResize /></label>
			</template>

			<template v-else-if="type === 'company_details'">
				<p class="text-xs text-gray-500 md:col-span-2">{{ ctrans("Fill in only what changed. We check every change before it is saved. To change the email, ask your contact at :group.", { group: String(usePage().props.group ?? "") }) }}</p>
				<label :class="label"><span :class="caption">{{ ctrans("Company name") }}</span><InputText v-model="form.name" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Main contact") }}</span><InputText v-model="form.contact_name" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Phone, with country code") }}</span><InputText v-model="form.phone" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Website") }}</span><InputText v-model="form.contact_website" /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Address") }}</span><Textarea v-model="form.address" rows="3" autoResize /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Note") }}</span><Textarea v-model="form.note" rows="2" autoResize /></label>
			</template>

			<template v-else-if="type === 'compliance_document'">
				<label :class="label"><span :class="caption">{{ ctrans("Document") }} *</span><Select v-model="form.document_type" :options="forms.document_types" optionLabel="label" optionValue="value" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Product, if it is about one") }}</span><Select v-model="form.supplier_product_id" :options="productOptions" optionLabel="label" optionValue="value" filter showClear /></label>
				<label :class="label"><span :class="caption">{{ ctrans("Expires on") }}</span><DatePicker v-model="form.expires_on" dateFormat="dd M yy" showIcon /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Note") }}</span><Textarea v-model="form.note" rows="2" autoResize /></label>
			</template>

			<template v-else-if="type === 'price_change'">
				<label :class="label">
					<span :class="caption">{{ ctrans("Product") }} *</span>
					<Select v-model="form.supplier_product_id" :options="productOptions" optionLabel="label" optionValue="value" filter :disabled="!!editing || lockedType === 'price_change'" />
					<span v-if="currentPrice !== null" class="text-xs text-gray-500">{{ ctrans("Current price per unit: :price", { price: locale.currencyFormatUnitCost(currency, currentPrice) }) }}</span>
				</label>
				<label :class="label"><span :class="caption">{{ ctrans("New price per unit, in :currency", { currency: currency ?? "" }) }} *</span><InputNumber v-model="form.proposed_price" v-bind="locale.unitCostInputOptions(currency)" /></label>
				<label :class="label"><span :class="caption">{{ ctrans("From") }}</span><DatePicker v-model="form.effective_from" dateFormat="dd M yy" showIcon /></label>
				<label :class="[label, 'md:col-span-2']"><span :class="caption">{{ ctrans("Why") }} *</span><Textarea v-model="form.reason" rows="2" autoResize /></label>
			</template>

			<div v-if="fieldsFor[type].includes('files')" class="flex flex-col gap-1 text-sm md:col-span-2">
				<span :class="caption">{{ type === "photos" ? ctrans("Pictures") : type === "compliance_document" ? ctrans("Files") : ctrans("Pictures and documents") }}</span>
				<FileUpload :key="fileKey" mode="basic" :auto="false" customUpload multiple :chooseLabel="ctrans('Choose files')" @select="(event) => (form.files = event.files)" />
				<span class="text-xs text-gray-400">{{ ctrans("Up to :count files, :mb MB each. JPG, PNG or PDF.", { count: String(forms.max_files), mb: String(forms.max_file_mb) }) }}</span>
				<progress v-if="form.progress" :value="form.progress.percentage" max="100" class="w-full">{{ form.progress.percentage }}%</progress>
			</div>

			<div v-if="Object.keys(form.errors).length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700 md:col-span-2">
				<p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
			</div>

			<div class="flex justify-end gap-2 md:col-span-2">
				<Button v-if="type === 'product_proposal' && (!editing || editing.state === 'draft')" type="tertiary" size="xs" :label="ctrans('Save draft')" :loading="form.processing" @click="send(true)">
					<template #icon><FontAwesomeIcon icon="fal fa-save" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="tertiary" size="xs" :label="ctrans('Send to :group', { group: String(usePage().props.group ?? '') })" :loading="form.processing" @click="send(false)">
					<template #icon><FontAwesomeIcon icon="fal fa-paper-plane" class="text-green-600" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</form>
	</div>
</template>
