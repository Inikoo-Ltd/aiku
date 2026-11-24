<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { useElementVisibility } from "@vueuse/core"
import axios from "axios"
import { debounce } from "lodash-es"
import InputText from "primevue/inputtext"
import Checkbox from "primevue/checkbox"
import Popover from "primevue/popover"
import Dialog from "primevue/dialog"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SupplierPortalForms from "@/Components/Procurement/SupplierPortalForms.vue"
import SupplierProductLabelsDialog from "@/Components/SupplyChain/SupplierProductLabelsDialog.vue"
import SupplierPortalProductWebsitesDialog from "@/Components/Procurement/SupplierPortalProductWebsitesDialog.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import type { EditedSubmission, PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faImage, faPen, faSearch, faFileAlt, faDownload } from "@fal"
import { routeType } from "@/types/route"

library.add(faBarcodeRead, faImage, faPen, faSearch, faFileAlt, faDownload)

interface ComplianceDetail {
	document: string | null
	product_code: string | null
	product_name: string | null
	expires_on: string | null
	note: string | null
	decided_at: string | null
	decided_by: string | null
	files: { name: string; ulid: string }[]
}

interface Product {
	id: number
	code: string
	name: string
	image: object | null
	image_full: { original_url: string; download_url: string } | null
	status: "active" | "on_hold" | "discontinued"
	status_label: string
	status_reason: string | null
	price: number
	units_per_pack: number
	packs: number
	sold: number
	previous: number
	change: number | null
}

interface ProductsPage {
	data: Product[]
	current_page: number
	last_page: number
	per_page: number
	total: number
}

interface Details {
	currency: string | null
	products: { id: number; code: string; name: string; price: number }[]
	terms: { term: string; product: string | null; value_type: string; amount: number | null; currency: string | null; text: string | null; agreed_on: string | null; valid_to: string | null }[]
	buyers: { organisations: string[]; name: string | null; email: string | null }[]
	submissions: (EditedSubmission & { type_label: string; state_label: string; summary: string; sent_at: string | null; created_at: string; editable: boolean; feedback?: string | null; detail?: ComplianceDetail | null })[]
	compliance: { document_type: string | null; product: string | null; expires_on: string | null; days_left: number | null; expiring: boolean }[]
}

const props = defineProps<{
	details: Details
	forms: PortalForms
	submitted: string | null
	section: "products" | "agreement" | "grow"
	initialFormType?: string
	initialSearch?: string
	productsRoute?: string
	productWebsitesRoute?: string
	submissionFileRoute?: string | null
	labelsPrint?: {
		plan_route: routeType
		pdf_route: routeType
	}
}>()

const search = ref(props.initialSearch ?? "")
watch(() => props.initialSearch, (value) => (search.value = value ?? ""))
const statusOptions = computed(() => [
	{ value: "all", label: ctrans("All") },
	{ value: "active", label: ctrans("Active") },
	{ value: "on_hold", label: ctrans("On hold") },
	{ value: "discontinued", label: ctrans("Discontinued") },
])
const status = ref<"all" | "active" | "on_hold" | "discontinued">("all")
const editing = ref<EditedSubmission | null>(null)
const sent = ref(props.submitted)
watch(() => props.submitted, (submitted) => (sent.value = submitted))

const productsPage = ref<ProductsPage | null>(null)
const products = ref<Product[]>([])
const isLoadingProducts = ref(false)
const isLoadingMoreProducts = ref(false)
const hasMoreProducts = computed(() => !!productsPage.value && productsPage.value.current_page < productsPage.value.last_page)
const productsError = ref<string | null>(null)
let latestProductsRequest = 0

const fetchProducts = async (page = 1) => {
	if (!props.productsRoute) {
		return
	}
	const request = ++latestProductsRequest
	const isNextPage = page > 1
	isLoadingProducts.value = !isNextPage
	isLoadingMoreProducts.value = isNextPage
	productsError.value = null
	try {
		const { data } = await axios.get<ProductsPage>(props.productsRoute, {
			params: { search: search.value.trim() || undefined, status: status.value, page },
		})
		if (request === latestProductsRequest) {
			productsPage.value = data
			products.value = isNextPage ? [...products.value, ...data.data] : data.data
		}
	} catch {
		if (request === latestProductsRequest) {
			productsError.value = ctrans("The products could not be loaded, try again")
		}
	} finally {
		if (request === latestProductsRequest) {
			isLoadingProducts.value = false
			isLoadingMoreProducts.value = false
		}
	}
}

const fetchProductsAfterTyping = debounce(() => fetchProducts(), 300)
watch(search, fetchProductsAfterTyping)
watch(status, () => fetchProducts())
watch(
	() => props.section,
	(section) => {
		if (section === "products" && !productsPage.value) {
			fetchProducts()
		}
	},
	{ immediate: true }
)

const mobileProductsEnd = ref<HTMLElement | null>(null)
const tableProductsEnd = ref<HTMLElement | null>(null)
const isMobileProductsEndVisible = useElementVisibility(mobileProductsEnd)
const isTableProductsEndVisible = useElementVisibility(tableProductsEnd)

watch([isMobileProductsEndVisible, isTableProductsEndVisible, isLoadingProducts, isLoadingMoreProducts], ([isMobileEndVisible, isTableEndVisible, isLoading, isLoadingMore]) => {
	if ((isMobileEndVisible || isTableEndVisible) && !isLoading && !isLoadingMore && !productsError.value && hasMoreProducts.value && productsPage.value) {
		fetchProducts(productsPage.value.current_page + 1)
	}
})

const openedProductId = ref<number | null>(null)

const selectedIds = ref<number[]>([])
const isLabelsDialogOpen = ref(false)
const areAllShownSelected = computed(() => products.value.length > 0 && products.value.every((product) => selectedIds.value.includes(product.id)))

const toggleAllShown = (isSelected: boolean) => {
	const shownIds = products.value.map((product) => product.id)
	selectedIds.value = isSelected
		? [...new Set([...selectedIds.value, ...shownIds])]
		: selectedIds.value.filter((id) => !shownIds.includes(id))
}

const money = (amount: number | null, currency: string | null = props.details.currency) =>
	amount === null ? "—" : currency ? new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: amount < 100 ? 2 : 0 }).format(amount) : String(amount)

const reasonPopover = ref<(InstanceType<typeof Popover> & { container: HTMLElement | null; alignOverlay: () => void }) | null>(null)
const shownReason = ref<string | null>(null)
const isReasonPinned = ref(false)

const openReason = async (event: Event, reason: string) => {
	shownReason.value = reason
	reasonPopover.value?.show(event, event.currentTarget as HTMLElement)
	await nextTick()
	if (reasonPopover.value?.container) {
		reasonPopover.value.alignOverlay()
	}
}

const showReason = (event: Event, reason: string) => {
	if (!isReasonPinned.value) {
		openReason(event, reason)
	}
}

const hideReason = () => {
	if (!isReasonPinned.value) {
		reasonPopover.value?.hide()
	}
}

const toggleReason = (event: Event, reason: string) => {
	if (isReasonPinned.value && shownReason.value === reason) {
		isReasonPinned.value = false
		reasonPopover.value?.hide()
		return
	}
	isReasonPinned.value = true
	openReason(event, reason)
}

const statusClass = { active: "text-green-700", on_hold: "text-amber-700", discontinued: "text-red-600" }
const stateClass: Record<string, string> = {
	draft: "text-gray-500",
	received: "text-gray-700",
	under_review: "text-gray-700",
	more_information_requested: "text-amber-700",
	accepted: "text-green-700",
	not_proceeding: "text-red-600",
}

type SentSubmission = Details["submissions"][number]
const openedSubmission = ref<SentSubmission | null>(null)
const openSubmission = (submission: SentSubmission) => {
	if (submission.detail) {
		openedSubmission.value = submission
	}
}
const fileUrl = (submission: SentSubmission, ulid: string): string | null =>
	props.submissionFileRoute ? props.submissionFileRoute.replace("/submissions/0/", `/submissions/${submission.id}/`).replace("/files/MEDIA", `/files/${ulid}`) : null

const sentHere = computed(() => props.details.submissions.filter((submission) => submission.type !== "price_change"))

const edit = (submission: EditedSubmission) => {
	editing.value = submission
	document.getElementById("grow-together")?.scrollIntoView({ behavior: "smooth" })
}
</script>

<template>
	<div class="space-y-4">
		<section v-if="section === 'products'" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<div class="flex flex-wrap items-end justify-between gap-2">
				<div>
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Your products") }}</h2>
					<p class="text-xs text-gray-500">{{ ctrans("Packs sold in the last 12 whole months at your price, against the 12 months before") }}</p>
					<p v-if="productWebsitesRoute" class="text-xs text-gray-500">{{ ctrans("Click a product to see how it is shown on our websites") }}</p>
				</div>
				<div class="flex flex-wrap items-center gap-2">
					<IconField>
						<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
						<InputText v-model="search" size="small" :placeholder="ctrans('Code or name')" :aria-label="ctrans('Search products')" />
					</IconField>
					<SegmentedToggle v-model="status" :options="statusOptions" soft class="max-w-full overflow-x-auto" :aria-label="ctrans('Status')" />
					<Button
						v-if="labelsPrint"
						type="tertiary"
						:label="ctrans('Print barcodes (:count)', { count: selectedIds.length })"
						:disabled="!selectedIds.length"
						:tooltip="selectedIds.length ? undefined : ctrans('Tick the products to print their barcodes')"
						@click="isLabelsDialogOpen = true"
					>
						<template #icon>
							<FontAwesomeIcon icon="fal fa-barcode-read" class="text-green-600" fixed-width aria-hidden="true" />
						</template>
					</Button>
				</div>
			</div>
			<p v-if="productsError" class="mt-3 text-xs text-red-600">{{ productsError }}</p>
			<div v-if="isLoadingProducts && !productsPage" class="mt-3 space-y-2">
				<div v-for="row in 6" :key="row" class="skeleton h-8 rounded" />
			</div>
			<p v-else-if="!products.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No products") }}</p>
			<ul v-else class="mt-3 divide-y divide-gray-100 md:hidden" :class="{ 'opacity-60': isLoadingProducts }">
				<li v-for="product in products" :key="product.id" class="flex gap-3 py-3" :class="{ 'bg-red-50': product.status === 'discontinued' }">
					<Checkbox v-if="labelsPrint" v-model="selectedIds" :value="product.id" :inputId="`portal-product-${product.id}`" :aria-label="product.code" class="mt-1" />
					<ZoomableImage v-if="product.image" :src="product.image" :full="product.image_full" :alt="product.code" class="h-14 w-14 shrink-0" />
					<div v-else class="h-14 w-14 shrink-0 rounded bg-gray-100" />
					<div class="min-w-0 flex-1 space-y-0.5">
						<div class="flex items-baseline justify-between gap-2">
							<button v-if="productWebsitesRoute" type="button" class="whitespace-nowrap font-semibold text-[var(--app-accent)] hover:underline" @click="openedProductId = product.id">{{ product.code }}</button>
							<span v-else class="whitespace-nowrap font-semibold text-gray-900">{{ product.code }}</span>
							<button
								v-if="product.status_reason"
								type="button"
								class="cursor-help text-sm underline decoration-dotted underline-offset-2"
								:class="statusClass[product.status]"
								:aria-label="`${product.status_label}: ${product.status_reason}`"
								@mouseenter="showReason($event, product.status_reason)"
								@mouseleave="hideReason"
								@click="toggleReason($event, product.status_reason)"
							>{{ product.status_label }}</button>
							<span v-else class="text-sm" :class="statusClass[product.status]">{{ product.status_label }}</span>
						</div>
						<div class="text-sm text-gray-600">{{ product.name }}</div>
						<div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm tabular-nums">
							<span class="text-gray-700">
								{{ money(product.price) }}<span v-if="product.units_per_pack > 1" class="text-xs text-gray-400"> × {{ product.units_per_pack }}</span>
							</span>
							<span class="text-gray-700">
								{{ ctrans(":count packs sold", { count: String(product.packs) }) }}
								<span v-if="product.change !== null" :class="product.change >= 0 ? 'text-green-700' : 'text-red-600'">{{ product.change >= 0 ? "+" : "" }}{{ product.change }}%</span>
								<span v-else-if="product.packs > 0" class="text-xs text-gray-400">{{ ctrans("New") }}</span>
							</span>
						</div>
					</div>
				</li>
			</ul>
			<div v-if="products.length && hasMoreProducts" ref="mobileProductsEnd" class="flex justify-center py-3 md:hidden"><LoadingIcon /></div>
			<div v-if="products.length" class="mt-3 hidden max-h-[32rem] overflow-auto md:block" :class="{ 'opacity-60': isLoadingProducts }">
				<table class="w-full text-sm">
					<thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th v-if="labelsPrint" class="w-8 pb-1 text-left">
								<Checkbox :modelValue="areAllShownSelected" binary :aria-label="ctrans('Select all')" @update:modelValue="toggleAllShown" />
							</th>
							<th class="whitespace-nowrap pb-1 text-left font-medium" colspan="2">{{ ctrans("Product") }}</th>
							<th class="whitespace-nowrap pb-1 pl-4 text-left font-medium">{{ ctrans("Status") }}</th>
							<th class="whitespace-nowrap pb-1 pl-6 text-right font-medium">{{ ctrans("Your price") }}</th>
							<th class="whitespace-nowrap pb-1 pl-6 text-right font-medium">{{ ctrans("Packs sold") }}</th>
							<th class="whitespace-nowrap pb-1 pl-6 text-right font-medium">{{ ctrans("Sold, at your prices") }}</th>
							<th class="whitespace-nowrap pb-1 pl-6 text-right font-medium">{{ ctrans("Difference") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
						<tr v-for="product in products" :key="product.id" :class="{ 'bg-red-50': product.status === 'discontinued' }">
							<td v-if="labelsPrint" class="w-8 py-1.5">
								<Checkbox v-model="selectedIds" :value="product.id" :aria-label="product.code" />
							</td>
							<td class="w-10 py-1.5 pr-2">
								<ZoomableImage v-if="product.image" :src="product.image" :full="product.image_full" :alt="product.code" class="h-8 w-8" />
								<div v-else class="h-8 w-8 rounded bg-gray-100 flex items-center justify-center">
									<FontAwesomeIcon icon='fal fa-image' class='text-xs text-gray-400' fixed-width aria-hidden='true' />
								</div>
							</td>
							<td class="py-1.5 pr-2">
								<button v-if="productWebsitesRoute" type="button" class="group text-left" :title="ctrans('See the websites selling this product')" @click="openedProductId = product.id">
									<span class="mr-1.5 whitespace-nowrap font-medium text-[var(--app-accent)] group-hover:underline">{{ product.code }}</span>
									<span class="text-gray-500">{{ product.name }}</span>
								</button>
								<template v-else>
									<span class="mr-1.5 whitespace-nowrap font-medium text-gray-800">{{ product.code }}</span>
									<span class="text-gray-500">{{ product.name }}</span>
								</template>
							</td>
							<td class="whitespace-nowrap py-1.5 pl-4" :class="statusClass[product.status]">
								<button
									v-if="product.status_reason"
									type="button"
									class="cursor-help underline decoration-dotted underline-offset-2"
									:aria-label="`${product.status_label}: ${product.status_reason}`"
									@mouseenter="showReason($event, product.status_reason)"
									@mouseleave="hideReason"
									@click="toggleReason($event, product.status_reason)"
								>{{ product.status_label }}</button>
								<template v-else>{{ product.status_label }}</template>
							</td>
							<td class="whitespace-nowrap py-1.5 pl-6 text-right">
								{{ money(product.price) }}
								<span v-if="product.units_per_pack > 1" class="text-xs text-gray-400"> × {{ product.units_per_pack }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5 pl-6 text-right">{{ product.packs }}</td>
							<td class="whitespace-nowrap py-1.5 pl-6 text-right">{{ money(product.sold) }}</td>
							<td class="whitespace-nowrap py-1.5 pl-6 text-right">
								<span v-if="product.change === null" class="text-xs text-gray-400">{{ product.packs > 0 ? ctrans("New") : "—" }}</span>
								<span v-else :class="product.change >= 0 ? 'text-green-700' : 'text-red-600'">{{ product.change >= 0 ? "+" : "" }}{{ product.change }}%</span>
							</td>
						</tr>
					</tbody>
				</table>
				<div v-if="hasMoreProducts" ref="tableProductsEnd" class="flex justify-center py-3"><LoadingIcon /></div>
			</div>
			<div v-if="productsPage && productsPage.total" class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
				<span class="tabular-nums">
					{{ ctrans(":count products", { count: String(productsPage.total) }) }}
					<LoadingIcon v-if="isLoadingProducts" class="ml-1" />
				</span>
			</div>
			<SupplierPortalProductWebsitesDialog v-if="productWebsitesRoute" v-model="openedProductId" :product-websites-route="productWebsitesRoute" />
			<SupplierProductLabelsDialog
				v-if="labelsPrint"
				v-model:visible="isLabelsDialogOpen"
				:selectedIds="selectedIds"
				:planRoute="labelsPrint.plan_route"
				:pdfRoute="labelsPrint.pdf_route"
			/>
		</section>

		<div v-if="section === 'agreement'" class="grid gap-4 md:grid-cols-2">
			<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Agreed terms") }}</h2>
				<p v-if="!details.terms.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("No terms shared with you yet") }}</p>
				<table v-else class="mt-2 w-full text-sm">
					<tbody class="divide-y divide-gray-100 text-gray-700">
						<tr v-for="(term, index) in details.terms" :key="index">
							<td class="py-1.5 pr-3">
								{{ term.term }}<span v-if="term.product" class="whitespace-nowrap text-xs text-gray-400"> · {{ term.product }}</span>
							</td>
							<td class="py-1.5 text-right">
								<template v-if="term.amount !== null">{{ money(term.amount, term.currency) }} <span class="text-xs text-gray-400">{{ term.value_type }}</span></template>
								<template v-else>{{ term.text ?? term.value_type }}</template>
								<div v-if="term.valid_to" class="text-xs text-gray-400">{{ ctrans("Until :date", { date: useFormatTime(term.valid_to) }) }}</div>
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Compliance documents") }}</h2>
				<p v-if="!details.compliance.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("No documents accepted yet. Send them in Grow together.") }}</p>
				<table v-else class="mt-2 w-full text-sm">
					<tbody class="divide-y divide-gray-100 text-gray-700">
						<tr v-for="(document, index) in details.compliance" :key="index">
							<td class="py-1.5 pr-3">
								{{ document.document_type }}<span v-if="document.product" class="whitespace-nowrap text-xs text-gray-400"> · {{ document.product }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5 text-right" :class="document.expiring ? 'text-amber-700' : 'text-gray-600'">
								<template v-if="document.expires_on">
									{{ document.days_left !== null && document.days_left < 0 ? ctrans("Expired :date", { date: useFormatTime(document.expires_on) }) : ctrans("Expires :date", { date: useFormatTime(document.expires_on) }) }}
								</template>
								<template v-else>{{ ctrans("No expiry") }}</template>
							</td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>

		<section v-if="section === 'grow'" id="grow-together" class="scroll-mt-40 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Let's grow together") }}</h2>
			<p class="mb-3 text-xs text-gray-500">{{ ctrans("Send us customers, better pictures, new products or updated details. Our team reviews everything and you can follow it below.") }}</p>
			<p v-if="sent" class="mb-3 rounded border border-green-200 bg-green-50 p-2 text-xs text-green-800">{{ ctrans("Thank you, we received it. You can follow it in the list below.") }}</p>
			<SupplierPortalForms :forms="forms" :products="details.products" :currency="details.currency" :editing="editing" :initial-type="initialFormType" @done="editing = null" />
		</section>

		<section v-if="section === 'grow'" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("What you sent us") }}</h2>
			<p v-if="!sentHere.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("Nothing yet") }}</p>
			<ul v-else class="mt-2 divide-y divide-gray-100 md:hidden">
				<li
					v-for="submission in sentHere"
					:key="submission.id"
					class="space-y-1 py-3 text-sm"
					:class="submission.detail ? 'cursor-pointer hover:bg-gray-50' : ''"
					@click="openSubmission(submission)"
				>
					<div class="flex items-baseline justify-between gap-2">
						<span class="font-semibold text-gray-900">{{ submission.type_label }}</span>
						<span :class="stateClass[submission.state]">{{ submission.state_label }}</span>
					</div>
					<div v-if="submission.summary" class="text-gray-600">{{ submission.summary }}</div>
					<div class="text-xs text-gray-400">{{ useFormatTime(submission.sent_at ?? submission.created_at) }}</div>
					<div v-if="submission.feedback" class="rounded bg-gray-50 p-2 text-gray-700">{{ submission.feedback }}</div>
					<Button v-if="submission.editable" type="tertiary" size="xs" :label="submission.state === 'draft' ? ctrans('Continue') : ctrans('Reply')" @click.stop="edit(submission)">
						<template #icon><FontAwesomeIcon icon="fal fa-pen" fixed-width aria-hidden="true" /></template>
					</Button>
				</li>
			</ul>
			<div v-if="sentHere.length" class="mt-2 hidden overflow-x-auto md:block">
				<table class="w-full text-sm">
					<thead class="text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Sent") }}</th>
							<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("What") }}</th>
							<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Status") }}</th>
							<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Our reply") }}</th>
							<th />
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 text-gray-700">
						<tr
							v-for="submission in sentHere"
							:key="submission.id"
							:class="submission.detail ? 'cursor-pointer hover:bg-gray-50' : ''"
							v-tooltip="submission.detail ? ctrans('See what you sent') : undefined"
							@click="openSubmission(submission)"
						>
							<td class="whitespace-nowrap py-1.5 pr-3">{{ useFormatTime(submission.sent_at ?? submission.created_at) }}</td>
							<td class="py-1.5 pr-3">
								{{ submission.type_label }}<span v-if="submission.summary" class="text-xs text-gray-400"> · {{ submission.summary }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5 pr-3" :class="stateClass[submission.state]">{{ submission.state_label }}</td>
							<td class="py-1.5 pr-3 text-xs text-gray-600">{{ submission.feedback ?? "" }}</td>
							<td class="py-1.5 text-right">
								<Button v-if="submission.editable" type="tertiary" size="xxs" :label="submission.state === 'draft' ? ctrans('Continue') : ctrans('Reply')" @click.stop="edit(submission)">
									<template #icon><FontAwesomeIcon icon="fal fa-pen" fixed-width aria-hidden="true" /></template>
								</Button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
		<Dialog
			:visible="!!openedSubmission"
			modal
			:header="openedSubmission?.type_label"
			:style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }"
			:draggable="false"
			@update:visible="(visible: boolean) => !visible && (openedSubmission = null)"
		>
			<div v-if="openedSubmission?.detail" class="space-y-3 text-sm">
				<dl class="grid grid-cols-[8rem_1fr] gap-x-3 gap-y-1.5">
					<dt class="text-gray-500">{{ ctrans("Document") }}</dt>
					<dd class="text-gray-800">{{ openedSubmission.detail.document ?? "-" }}</dd>
					<dt class="text-gray-500">{{ ctrans("Product") }}</dt>
					<dd class="text-gray-800">
						<span class="whitespace-nowrap font-medium">{{ openedSubmission.detail.product_code ?? "-" }}</span>
						<span v-if="openedSubmission.detail.product_name" class="text-gray-500"> · {{ openedSubmission.detail.product_name }}</span>
					</dd>
					<dt class="text-gray-500">{{ ctrans("Expires on") }}</dt>
					<dd class="text-gray-800">{{ openedSubmission.detail.expires_on ? useFormatTime(openedSubmission.detail.expires_on) : "-" }}</dd>
					<dt class="text-gray-500">{{ ctrans("Sent") }}</dt>
					<dd class="text-gray-800">{{ useFormatTime(openedSubmission.sent_at ?? openedSubmission.created_at) }}</dd>
					<dt class="text-gray-500">{{ ctrans("Status") }}</dt>
					<dd :class="stateClass[openedSubmission.state]">
						{{ openedSubmission.state_label }}<span v-if="openedSubmission.detail.decided_at" class="text-xs text-gray-400"> · {{ openedSubmission.detail.decided_by ? ctrans(":date by :user", { date: useFormatTime(openedSubmission.detail.decided_at), user: openedSubmission.detail.decided_by }) : useFormatTime(openedSubmission.detail.decided_at) }}</span>
					</dd>
					<template v-if="openedSubmission.detail.note">
						<dt class="text-gray-500">{{ ctrans("Your note") }}</dt>
						<dd class="whitespace-pre-line text-gray-800">{{ openedSubmission.detail.note }}</dd>
					</template>
				</dl>

				<div v-if="openedSubmission.feedback" class="rounded bg-gray-50 p-2">
					<p class="text-xs font-medium text-gray-500">{{ ctrans("Our reply") }}</p>
					<p class="whitespace-pre-line text-gray-700">{{ openedSubmission.feedback }}</p>
				</div>

				<div>
					<p class="mb-1 text-xs font-medium text-gray-500">{{ ctrans("Files") }}</p>
					<p v-if="!openedSubmission.detail.files.length" class="text-xs text-gray-400">{{ ctrans("No files") }}</p>
					<ul v-else class="divide-y divide-gray-100 rounded-md border border-gray-200">
						<li v-for="file in openedSubmission.detail.files" :key="file.ulid" class="flex items-center gap-2 px-3 py-1.5">
							<FontAwesomeIcon :icon="faFileAlt" class="text-gray-400" fixed-width aria-hidden="true" />
							<span class="min-w-0 flex-1 truncate text-gray-700">{{ file.name }}</span>
							<a
								v-if="fileUrl(openedSubmission, file.ulid)"
								:href="fileUrl(openedSubmission, file.ulid) as string"
								class="text-gray-400 hover:text-[var(--app-accent)]"
								v-tooltip="ctrans('Download')"
							>
								<FontAwesomeIcon :icon="faDownload" fixed-width aria-hidden="true" />
							</a>
						</li>
					</ul>
				</div>
			</div>
		</Dialog>

		<Popover ref="reasonPopover" @hide="isReasonPinned = false">
			<p class="max-w-xs whitespace-pre-line text-xs text-gray-700">{{ shownReason }}</p>
		</Popover>
	</div>
</template>
