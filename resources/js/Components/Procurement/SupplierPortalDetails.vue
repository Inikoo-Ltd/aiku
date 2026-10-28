<script setup lang="ts">
import { computed, ref, watch } from "vue"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import SupplierPortalForms from "@/Components/Procurement/SupplierPortalForms.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import type { EditedSubmission, PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPen, faSearch } from "@fal"

library.add(faPen, faSearch)

interface Details {
	currency: string | null
	products: {
		id: number
		code: string
		name: string
		image: object | null
		status: "active" | "on_hold" | "discontinued"
		status_label: string
		price: number
		units_per_pack: number
		packs: number
		sold: number
		previous: number
		change: number | null
	}[]
	terms: { term: string; product: string | null; value_type: string; amount: number | null; currency: string | null; text: string | null; agreed_on: string | null; valid_to: string | null }[]
	buyers: { organisation: string; name: string | null }[]
	submissions: (EditedSubmission & { type_label: string; state_label: string; summary: string; sent_at: string | null; created_at: string; editable: boolean })[]
	compliance: { document_type: string | null; product: string | null; expires_on: string | null; days_left: number | null; expiring: boolean }[]
}

const props = defineProps<{
	details: Details
	forms: PortalForms
	submitted: string | null
	section: "products" | "agreement" | "grow"
	initialFormType?: string
	initialSearch?: string
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

const products = computed(() =>
	props.details.products.filter(
		(product) =>
			(status.value === "all" || product.status === status.value) &&
			(!search.value || `${product.code} ${product.name}`.toLowerCase().includes(search.value.toLowerCase()))
	)
)

const money = (amount: number | null, currency: string | null = props.details.currency) =>
	amount === null ? "—" : currency ? new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: amount < 100 ? 2 : 0 }).format(amount) : String(amount)

const statusClass = { active: "text-green-700", on_hold: "text-amber-700", discontinued: "text-gray-400" }
const stateClass: Record<string, string> = {
	draft: "text-gray-500",
	received: "text-gray-700",
	under_review: "text-gray-700",
	more_information_requested: "text-amber-700",
	accepted: "text-green-700",
	not_proceeding: "text-red-600",
}

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
				</div>
				<div class="flex flex-wrap items-center gap-2">
					<IconField>
						<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
						<InputText v-model="search" size="small" :placeholder="ctrans('Code or name')" :aria-label="ctrans('Search products')" />
					</IconField>
					<SegmentedToggle v-model="status" :options="statusOptions" soft class="max-w-full overflow-x-auto" :aria-label="ctrans('Status')" />
				</div>
			</div>
			<p v-if="!products.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No products") }}</p>
			<ul v-else class="mt-3 divide-y divide-gray-100 md:hidden">
				<li v-for="product in products" :key="product.id" class="flex gap-3 py-3">
					<Image v-if="product.image" :src="product.image" class="h-14 w-14 shrink-0 rounded object-cover" :alt="product.code" />
					<div v-else class="h-14 w-14 shrink-0 rounded bg-gray-100" />
					<div class="min-w-0 flex-1 space-y-0.5">
						<div class="flex items-baseline justify-between gap-2">
							<span class="font-semibold text-gray-900">{{ product.code }}</span>
							<span class="text-sm" :class="statusClass[product.status]">{{ product.status_label }}</span>
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
			<div v-if="products.length" class="mt-3 hidden max-h-[32rem] overflow-auto md:block">
				<table class="w-full text-sm">
					<thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th class="whitespace-nowrap pb-1 text-left font-medium" colspan="2">{{ ctrans("Product") }}</th>
							<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Status") }}</th>
							<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Your price") }}</th>
							<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Packs sold") }}</th>
							<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Sold, at your prices") }}</th>
							<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Difference") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
						<tr v-for="product in products" :key="product.id">
							<td class="w-10 py-1.5 pr-2">
								<Image v-if="product.image" :src="product.image" class="h-8 w-8 rounded object-cover" :alt="product.code" />
								<div v-else class="h-8 w-8 rounded bg-gray-100" />
							</td>
							<td class="py-1.5">
								<span class="mr-1.5 whitespace-nowrap font-medium text-gray-800">{{ product.code }}</span>
								<span class="text-gray-500">{{ product.name }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5" :class="statusClass[product.status]">{{ product.status_label }}</td>
							<td class="whitespace-nowrap py-1.5 text-right">
								{{ money(product.price) }}
								<span v-if="product.units_per_pack > 1" class="text-xs text-gray-400"> × {{ product.units_per_pack }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5 text-right">{{ product.packs }}</td>
							<td class="whitespace-nowrap py-1.5 text-right">{{ money(product.sold) }}</td>
							<td class="whitespace-nowrap py-1.5 text-right">
								<span v-if="product.change === null" class="text-xs text-gray-400">{{ product.packs > 0 ? ctrans("New") : "—" }}</span>
								<span v-else :class="product.change >= 0 ? 'text-green-700' : 'text-red-600'">{{ product.change >= 0 ? "+" : "" }}{{ product.change }}%</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
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
			<p v-if="!details.submissions.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("Nothing yet") }}</p>
			<ul v-else class="mt-2 divide-y divide-gray-100 md:hidden">
				<li v-for="submission in details.submissions" :key="submission.id" class="space-y-1 py-3 text-sm">
					<div class="flex items-baseline justify-between gap-2">
						<span class="font-semibold text-gray-900">{{ submission.type_label }}</span>
						<span :class="stateClass[submission.state]">{{ submission.state_label }}</span>
					</div>
					<div v-if="submission.summary" class="text-gray-600">{{ submission.summary }}</div>
					<div class="text-xs text-gray-400">{{ useFormatTime(submission.sent_at ?? submission.created_at) }}</div>
					<div v-if="submission.feedback" class="rounded bg-gray-50 p-2 text-gray-700">{{ submission.feedback }}</div>
					<Button v-if="submission.editable" type="tertiary" size="xs" :label="submission.state === 'draft' ? ctrans('Continue') : ctrans('Reply')" @click="edit(submission)">
						<template #icon><FontAwesomeIcon icon="fal fa-pen" fixed-width aria-hidden="true" /></template>
					</Button>
				</li>
			</ul>
			<div v-if="details.submissions.length" class="mt-2 hidden overflow-x-auto md:block">
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
						<tr v-for="submission in details.submissions" :key="submission.id">
							<td class="whitespace-nowrap py-1.5 pr-3">{{ useFormatTime(submission.sent_at ?? submission.created_at) }}</td>
							<td class="py-1.5 pr-3">
								{{ submission.type_label }}<span v-if="submission.summary" class="text-xs text-gray-400"> · {{ submission.summary }}</span>
							</td>
							<td class="whitespace-nowrap py-1.5 pr-3" :class="stateClass[submission.state]">{{ submission.state_label }}</td>
							<td class="py-1.5 pr-3 text-xs text-gray-600">{{ submission.feedback ?? "" }}</td>
							<td class="py-1.5 text-right">
								<Button v-if="submission.editable" type="tertiary" size="xxs" :label="submission.state === 'draft' ? ctrans('Continue') : ctrans('Reply')" @click="edit(submission)">
									<template #icon><FontAwesomeIcon icon="fal fa-pen" fixed-width aria-hidden="true" /></template>
								</Button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</template>
