<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLayerGroup, faFileExcel, faFilePdf, faFileInvoice, faShippingFast, faBan } from "@fal"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
library.add(faLayerGroup, faFileExcel, faFilePdf, faFileInvoice, faShippingFast, faBan)

type RouteTarget = { name: string; parameters: (string | number)[] }

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	truck: {
		reference: string
		partner: string
		date: string
		number_pallets: number | null
		state: "loading" | "customs_ready" | "dispatched" | "void"
		void_note: string | null
		dispatched_at: string | null
		weight: number
		number_parcels: number
	}
	number_notes: number
	waiting: string[]
	customsInvoices: { reference: string; label: string; lines: number; units: number; amount: number; currency: string | null; gross_weight: number; download_url: string }[]
	packingListUrl: string
	canWork: boolean
	canVoid: boolean
	routes: { customs: RouteTarget; dispatch: RouteTarget; void: RouteTarget }
	data: object
}>()

const stateLabels = {
	loading: ctrans("Open"),
	customs_ready: ctrans("Customs invoices ready"),
	dispatched: ctrans("Dispatched"),
	void: ctrans("Void"),
}

const formatNumber = (value: number, digits = 0) => value.toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits })

const busy = ref<string | null>(null)
const error = ref<string | null>(null)

function send(key: "customs" | "dispatch", method: "post" | "patch") {
	const target = props.routes[key]
	router[method](
		route(target.name, target.parameters),
		{},
		{
			preserveScroll: true,
			onStart: () => {
				busy.value = key
				error.value = null
			},
			onError: (errors) => (error.value = Object.values(errors).join(" ")),
			onFinish: () => (busy.value = null),
		}
	)
}

const isVoiding = ref(false)
const voidReason = ref("")
const voidError = ref<string | null>(null)

function submitVoid() {
	router.patch(
		route(props.routes.void.name, props.routes.void.parameters),
		{ void_reason: voidReason.value },
		{
			preserveScroll: true,
			onStart: () => (busy.value = "void"),
			onSuccess: () => (isVoiding.value = false),
			onError: (errors) => (voidError.value = Object.values(errors).join(" ")),
			onFinish: () => (busy.value = null),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3 text-sm">
		<span class="rounded px-2 py-0.5 text-xs font-medium" :class="truck.state === 'void' ? 'bg-red-50 text-red-700' : truck.state === 'dispatched' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'">
			{{ stateLabels[truck.state] }}
		</span>
		<span class="text-gray-500">{{ ctrans("Date") }} <span class="text-gray-800">{{ truck.date }}</span></span>
		<span class="text-gray-500">{{ ctrans("Delivery notes") }} <span class="text-gray-800">{{ number_notes }}</span></span>
		<span class="text-gray-500">{{ ctrans("Parcels") }} <span class="text-gray-800">{{ truck.number_parcels }}</span></span>
		<span class="text-gray-500">{{ ctrans("Pallets") }} <span class="text-gray-800">{{ truck.number_pallets ?? "-" }}</span></span>
		<span class="text-gray-500">{{ ctrans("Weight") }} <span class="text-gray-800">{{ formatNumber(truck.weight, 1) }} kg</span></span>
		<span v-if="truck.dispatched_at" class="text-gray-500">{{ ctrans("Dispatched") }} <span class="text-gray-800">{{ truck.dispatched_at }}</span></span>
	</div>

	<p v-if="truck.void_note" class="mx-4 mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ truck.void_note }}</p>

	<div v-if="truck.state !== 'void'" class="flex flex-wrap items-center gap-2 px-4 pb-3">
		<a :href="packingListUrl + '?format=pdf'" target="_blank">
			<Button type="tertiary" :label="ctrans('Consolidated delivery note')">
				<template #icon>
					<FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</a>
		<a :href="packingListUrl" download>
			<Button type="tertiary" label="XLSX">
				<template #icon>
					<FontAwesomeIcon :icon="faFileExcel" fixed-width aria-hidden="true" />
				</template>
			</Button>
		</a>
		<Button
			v-if="canWork && truck.state === 'loading'"
			type="tertiary"
			:label="ctrans('Make customs invoices')"
			:loading="busy === 'customs'"
			:disabled="waiting.length > 0"
			@click="send('customs', 'post')">
			<template #icon>
				<FontAwesomeIcon :icon="faFileInvoice" fixed-width aria-hidden="true" />
			</template>
		</Button>
		<Button v-if="canWork && truck.state === 'customs_ready'" type="tertiary" :label="ctrans('Dispatch all')" :loading="busy === 'dispatch'" @click="send('dispatch', 'patch')">
			<template #icon>
				<FontAwesomeIcon :icon="faShippingFast" fixed-width aria-hidden="true" />
			</template>
		</Button>
		<Button v-if="canVoid" type="tertiary" :label="ctrans('Void')" @click="(isVoiding = true), (voidReason = ''), (voidError = null)">
			<template #icon>
				<FontAwesomeIcon :icon="faBan" fixed-width aria-hidden="true" />
			</template>
		</Button>
	</div>

	<p v-if="waiting.length" class="mx-4 mb-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
		{{ ctrans("Waiting for invoices: :notes. The customs invoices can be made once they are finalised.", { notes: waiting.join(", ") }) }}
	</p>
	<p v-if="error" class="mx-4 mb-3 text-sm text-red-600">{{ error }}</p>

	<div v-if="customsInvoices.length" class="grid gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-3">
		<div v-for="customsInvoice in customsInvoices" :key="customsInvoice.reference" class="rounded border border-gray-200 px-3 py-2">
			<div class="flex items-center gap-2 font-medium" :class="truck.state === 'void' ? 'text-gray-400 line-through' : 'text-gray-800'">
				{{ customsInvoice.reference }}
				<span class="text-xs font-normal text-gray-500">{{ customsInvoice.label }}</span>
				<span class="ml-auto flex gap-2 text-gray-500">
					<a :href="customsInvoice.download_url + '?format=pdf'" target="_blank" class="hover:text-[var(--app-accent)]" v-tooltip="'PDF'">
						<FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true" />
					</a>
					<a :href="customsInvoice.download_url" download class="hover:text-[var(--app-accent)]" v-tooltip="'XLSX'">
						<FontAwesomeIcon :icon="faFileExcel" fixed-width aria-hidden="true" />
					</a>
				</span>
			</div>
			<div class="mt-1 text-xs text-gray-500">
				{{ customsInvoice.lines }} {{ ctrans("lines") }} · {{ formatNumber(customsInvoice.units) }} {{ ctrans("units") }} · {{ customsInvoice.currency }}
				{{ formatNumber(customsInvoice.amount, 2) }} · {{ formatNumber(customsInvoice.gross_weight, 1) }} kg
			</div>
		</div>
	</div>

	<Table v-if="truck.state !== 'void'" :resource="data">
		<template #cell(reference)="{ item }">
			<span class="whitespace-nowrap">{{ item.reference }}</span>
		</template>
		<template #cell(invoice)="{ item }">
			<span class="whitespace-nowrap" :class="item.is_invoiced ? '' : 'text-amber-600'">{{ item.invoice }}</span>
		</template>
	</Table>

	<Dialog v-model:visible="isVoiding" modal :header="ctrans('Void consolidated delivery note')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div class="space-y-3 text-sm">
			<p>{{ ctrans("This voids :reference and its customs invoices.", { reference: truck.reference }) }}</p>
			<p class="text-gray-500">
				{{ ctrans("They stay listed with their numbers, marked void, and the numbers are not used again. Its delivery notes and invoices are freed so you can consolidate them again.") }}
			</p>
			<label class="flex flex-col gap-1 text-xs text-gray-500">
				{{ ctrans("Why") }}
				<Textarea v-model="voidReason" rows="2" autoResize class="w-full text-sm" />
			</label>
			<p v-if="voidError" class="text-xs text-red-500">{{ voidError }}</p>
			<div class="flex justify-end gap-2">
				<Button type="tertiary" :label="ctrans('Cancel')" @click="isVoiding = false" />
				<Button type="delete" :icon="faBan" :label="ctrans('Void')" :loading="busy === 'void'" :disabled="voidReason.trim().length < 3" @click="submitVoid" />
			</div>
		</div>
	</Dialog>
</template>
