<script setup lang="ts">
import { computed } from "vue"
import { Head, Link, useForm } from "@inertiajs/vue3"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import Checkbox from "primevue/checkbox"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInboxIn, faCheck, faPaperclip, faExclamationTriangle } from "@fal"

library.add(faInboxIn, faCheck, faPaperclip, faExclamationTriangle)

interface RouteTarget {
	name: string
	parameters: Record<string, number>
}

const props = defineProps<{
	title: string
	pageHead: object
	submission: {
		id: number
		type: string
		type_label: string
		state: string
		state_label: string
		data: Record<string, any>
		current: Record<string, string | null>
		feedback: string | null
		expires_on: string | null
		submitted_at: string | null
		decided_at: string | null
		decided_by: string | null
		applied: Record<string, any> | null
		applied_at: string | null
		applied_to?: { label: string; url: string }[]
		sent_by: string | null
		supplier: { code: string; name: string; url: string }
		product: { code: string; name: string; cost: number; currency: string | null } | null
		files: { name: string; url: string; image: boolean }[]
		duplicates: { kind: string; name: string | null; email: string | null }[]
	}
	decide: {
		can: boolean
		route: RouteTarget
		states: { value: string; label: string }[]
		applies: boolean
		shops: { value: number; label: string }[]
		company_fields: string[]
		designations: Record<string, string>
	}
}>()

const form = useForm({
	state: props.submission.state === "received" ? "under_review" : props.submission.state,
	feedback: props.submission.feedback ?? "",
	shop_id: null as number | null,
	fields: props.decide.company_fields.filter((field) => props.submission.data[field]),
})

const isAccepting = computed(() => form.state === "accepted" && !props.submission.applied_at)
const fieldLabels: Record<string, string> = {
	name: props.submission.type === "company_details" ? ctrans("Company name") : props.submission.type === "product_proposal" ? ctrans("Product name") : ctrans("Name"),
	contact_name: ctrans("Main contact"),
	email: ctrans("Email"),
	phone: ctrans("Phone"),
	contact_website: ctrans("Website"),
	address: ctrans("Address"),
	company_name: ctrans("Business name"),
	country: ctrans("Country"),
	products_of_interest: ctrans("Products of interest"),
	note: ctrans("Note"),
	picture_kind: ctrans("Kind of picture"),
	description: ctrans("Description"),
	proposed_price: ctrans("Proposed price"),
	currency: ctrans("Currency"),
	unit_basis: ctrans("Price is per"),
	moq: ctrans("Minimum order"),
	lead_time_days: ctrans("Lead time, days"),
	materials: ctrans("Materials"),
	dimensions: ctrans("Dimensions"),
	packaging: ctrans("Packaging"),
	document_type: ctrans("Document"),
	effective_from: ctrans("From"),
	effective_until: ctrans("Until"),
	reason: ctrans("Why"),
	material: ctrans("Material"),
	reserve_by: ctrans("Reserved until"),
	permission_confirmed: ctrans("Permission to share confirmed"),
	designation: ctrans("Designation"),
	mobile: ctrans("Mobile number"),
	whatsapp: "WhatsApp",
	wechat: "WeChat",
}

const locale = useLocaleStore()
const priceCurrency = computed(() => props.submission.data.currency ?? props.submission.product?.currency ?? "")

const fieldValue = (field: string, value: unknown) => {
	if (field === "designation") {
		return props.decide.designations[value as string] ?? value
	}
	if (field === "proposed_price") {
		return locale.currencyFormatUnitCost(priceCurrency.value, value as string)
	}
	if (value === true || value === 1 || value === "1") {
		return ctrans("Yes")
	}
	return value
}

const HIDDEN_FIELDS = ["plan", "escalated_for"]
const shownData = computed(() => Object.fromEntries(Object.entries(props.submission.data).filter(([field]) => !HIDDEN_FIELDS.includes(field))))

const plan = computed(() => props.submission.data.plan as { as_of: string; status: string; order_from: string | null; order_to: string | null; arrive_from: string | null; arrive_to: string | null; units_low: number; units_high: number; cartons_low: number | null; cartons_high: number | null } | undefined)
const planDay = (date: string | null) => (date ? useFormatTime(date, { formatTime: "dd MMM yyyy" }) : "—")
const planSpan = (low: number | null, high: number | null) => (low === high ? locale.number(low ?? 0) : `${locale.number(low ?? 0)} – ${locale.number(high ?? 0)}`)

const save = () => form.post(route(props.decide.route.name, props.decide.route.parameters), { preserveScroll: true })
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="grid gap-4 p-4 lg:grid-cols-3">
			<section class="space-y-4 lg:col-span-2">
				<div class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
					<div class="mb-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500">
						<span>{{ ctrans("Supplier") }}: <Link :href="submission.supplier.url" class="primaryLink whitespace-nowrap">{{ submission.supplier.code }}</Link> {{ submission.supplier.name }}</span>
						<span v-if="submission.sent_by">{{ ctrans("Sent by :email", { email: submission.sent_by }) }}</span>
						<span v-if="submission.submitted_at">{{ useFormatTime(submission.submitted_at) }}</span>
						<span>{{ submission.state_label }}</span>
					</div>

					<p v-if="submission.product" class="mb-2">
						{{ ctrans("Product") }}: <span class="whitespace-nowrap font-medium">{{ submission.product.code }}</span> {{ submission.product.name }}
						<span class="text-xs text-gray-500"> · {{ ctrans("current price :price", { price: locale.currencyFormatUnitCost(submission.product.currency, submission.product.cost) }) }}</span>
					</p>
					<p v-if="submission.expires_on" class="mb-2">{{ ctrans("Expires on") }}: {{ useFormatTime(submission.expires_on) }}</p>

					<table class="w-full">
						<thead v-if="submission.type === 'company_details'" class="whitespace-nowrap text-xs text-gray-400">
							<tr>
								<th class="py-1 text-left font-normal" />
								<th class="py-1 text-left font-normal">{{ ctrans("Now") }}</th>
								<th class="py-1 text-left font-normal">{{ ctrans("Supplier asks") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="(value, field) in shownData" :key="field">
								<td class="w-48 py-1.5 pr-3 align-top text-gray-500">{{ fieldLabels[field] ?? field }}</td>
								<td v-if="submission.type === 'company_details'" class="py-1.5 pr-3 align-top text-gray-500">{{ submission.current[field] ?? "—" }}</td>
								<td class="whitespace-pre-line py-1.5 align-top text-gray-800">{{ fieldValue(field, value) }}</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div v-if="plan" class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
					<h2 class="mb-2 text-xs font-semibold text-gray-600">{{ ctrans("Plan for next orders when it was sent, :date", { date: planDay(plan.as_of) }) }}</h2>
					<p v-if="plan.status !== 'estimate'" class="text-gray-600">{{ ctrans("No order currently indicated") }}</p>
					<dl v-else class="grid gap-x-6 gap-y-1 sm:grid-cols-3">
						<div><dt class="text-xs text-gray-400">{{ ctrans("Order window") }}</dt><dd>{{ planDay(plan.order_from) }} – {{ planDay(plan.order_to) }}</dd></div>
						<div><dt class="text-xs text-gray-400">{{ ctrans("Delivery window") }}</dt><dd>{{ planDay(plan.arrive_from) }} – {{ planDay(plan.arrive_to) }}</dd></div>
						<div>
							<dt class="text-xs text-gray-400">{{ ctrans("Quantity") }}</dt>
							<dd class="tabular-nums">
								{{ ctrans(":units units", { units: planSpan(plan.units_low, plan.units_high) }) }}
								<span v-if="plan.cartons_low !== null" class="text-xs text-gray-500">· {{ ctrans(":cartons cartons", { cartons: planSpan(plan.cartons_low, plan.cartons_high) }) }}</span>
							</dd>
						</div>
					</dl>
				</div>

				<div v-if="submission.files.length" class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
					<h2 class="mb-2 text-xs font-semibold text-gray-600">{{ ctrans("Files") }}</h2>
					<div class="flex flex-wrap gap-3">
						<a v-for="file in submission.files" :key="file.url" :href="file.url" target="_blank" class="flex flex-col items-center gap-1 text-xs text-gray-700 hover:underline">
							<img v-if="file.image" :src="file.url + '?inline=1'" class="h-28 w-28 rounded border border-gray-200 object-cover" :alt="file.name" />
							<FontAwesomeIcon v-else icon="fal fa-paperclip" class="text-gray-400" fixed-width aria-hidden="true" />
							<span class="max-w-28 truncate">{{ file.name }}</span>
						</a>
					</div>
				</div>

				<div v-if="submission.duplicates.length" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
					<p class="flex items-center gap-2 font-semibold"><FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />{{ ctrans("Already in aiku") }}</p>
					<p v-for="(duplicate, index) in submission.duplicates" :key="index" class="text-xs">{{ duplicate.kind }}: {{ duplicate.name }} · {{ duplicate.email }}</p>
				</div>
			</section>

			<section class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 text-sm">
				<h2 class="text-xs font-semibold text-gray-600">{{ ctrans("Decision") }}</h2>
				<p v-if="submission.decided_at" class="text-xs text-gray-500">{{ ctrans("Last change :date by :user", { date: useFormatTime(submission.decided_at), user: submission.decided_by ?? "" }) }}</p>
				<p v-if="submission.applied_at" class="text-xs text-green-700">{{ ctrans("Applied in aiku :date", { date: useFormatTime(submission.applied_at) }) }}</p>
				<p v-if="submission.applied_to?.length" class="flex flex-wrap items-center gap-x-2 text-xs text-gray-600">
					<span>{{ ctrans("Added to the attachments of") }}</span>
					<Link v-for="target in submission.applied_to" :key="target.url" :href="target.url" class="primaryLink whitespace-nowrap">{{ target.label }}</Link>
				</p>

				<template v-if="decide.can">
					<label class="flex flex-col gap-1">
						<span class="font-medium text-gray-700">{{ ctrans("State") }}</span>
						<Select v-model="form.state" :options="decide.states" optionLabel="label" optionValue="value" />
					</label>
					<label class="flex flex-col gap-1">
						<span class="font-medium text-gray-700">{{ ctrans("Reply to the supplier") }}</span>
						<Textarea v-model="form.feedback" rows="4" autoResize />
						<span class="text-xs text-gray-400">{{ ctrans("The supplier reads this on its page.") }}</span>
					</label>

					<template v-if="isAccepting && decide.applies">
						<div v-if="submission.type === 'company_details'" class="space-y-1">
							<span class="font-medium text-gray-700">{{ ctrans("Change on the supplier") }}</span>
							<label v-for="field in decide.company_fields.filter((field) => submission.data[field])" :key="field" class="flex items-center gap-2">
								<Checkbox v-model="form.fields" :value="field" />{{ fieldLabels[field] }}
							</label>
							<p v-if="submission.data.address" class="text-xs text-gray-500">{{ ctrans("Change the address by hand on the supplier.") }}</p>
						</div>
						<label v-if="submission.type === 'referral'" class="flex flex-col gap-1">
							<span class="font-medium text-gray-700">{{ ctrans("Add as prospect of") }}</span>
							<Select v-model="form.shop_id" :options="decide.shops" optionLabel="label" optionValue="value" filter />
						</label>
						<p v-if="submission.type === 'photos'" class="text-xs text-gray-500">{{ ctrans("The pictures are added to the supplier product, for internal use.") }}</p>
						<p v-if="submission.type === 'compliance_document'" class="text-xs text-gray-500">{{ ctrans("The files are added to the supplier's attachments.") }}</p>
						<p v-if="submission.type === 'contact'" class="text-xs text-gray-500">{{ ctrans("The email is added to the supplier's emails: the person can sign in to the supplier page and gets the newsletter.") }}</p>
						<p v-if="submission.type === 'price_change'" class="text-xs text-gray-500">{{ ctrans("The proposed price becomes the product cost, and the product is no longer on hold.") }}</p>
					</template>
					<p v-else-if="isAccepting && submission.type === 'material_planning'" class="text-xs text-gray-500">{{ ctrans("Accepting a planning note changes nothing in aiku and approves no compliance: the documents stay on this note only.") }}</p>
					<p v-else-if="isAccepting" class="text-xs text-gray-500">{{ ctrans("Accepting changes nothing in aiku by itself: create the product by hand.") }}</p>

					<div v-if="Object.keys(form.errors).length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
						<p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
					</div>
					<div class="flex justify-end">
						<Button xtype="tertiary" size="xs" :label="ctrans('Save')" :loading="form.processing" @click="save" full key="2">
						</Button>
					</div>
				</template>
				<p v-else class="text-xs text-gray-500">{{ ctrans("Only supply chain editors can decide.") }}</p>
			</section>
		</div>
	</div>
</template>
