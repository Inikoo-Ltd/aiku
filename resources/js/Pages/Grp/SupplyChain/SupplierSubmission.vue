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
	name: ctrans("Company name"),
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
	reason: ctrans("Why"),
	permission_confirmed: ctrans("Permission to share confirmed"),
}

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
						<span class="text-xs text-gray-500"> · {{ ctrans("current price :price :currency", { price: String(submission.product.cost), currency: submission.product.currency ?? "" }) }}</span>
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
							<tr v-for="(value, field) in submission.data" :key="field">
								<td class="w-48 py-1.5 pr-3 align-top text-gray-500">{{ fieldLabels[field] ?? field }}</td>
								<td v-if="submission.type === 'company_details'" class="py-1.5 pr-3 align-top text-gray-500">{{ submission.current[field] ?? "—" }}</td>
								<td class="whitespace-pre-line py-1.5 align-top text-gray-800">{{ value === true || value === 1 || value === "1" ? ctrans("Yes") : value }}</td>
							</tr>
						</tbody>
					</table>
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
						<p v-if="submission.type === 'price_change'" class="text-xs text-gray-500">{{ ctrans("The proposed price becomes the product cost, and the product is no longer on hold.") }}</p>
					</template>
					<p v-else-if="isAccepting" class="text-xs text-gray-500">{{ ctrans("Accepting changes nothing in aiku by itself: create the product by hand.") }}</p>

					<div v-if="Object.keys(form.errors).length" class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-700">
						<p v-for="(error, field) in form.errors" :key="field">{{ error }}</p>
					</div>
					<div class="flex justify-end">
						<Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="form.processing" @click="save">
							<template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
						</Button>
					</div>
				</template>
				<p v-else class="text-xs text-gray-500">{{ ctrans("Only supply chain editors can decide.") }}</p>
			</section>
		</div>
	</div>
</template>
