<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import axios from "axios"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import BankLinesFilters from "@/Components/Accounting/BankLinesFilters.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUndo, faUniversity, faCheck, faTimes, faTags, faBuilding, faLandmark, faCreditCard, faTruckContainer } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUndo, faUniversity, faCheck, faTimes, faTags, faBuilding, faLandmark, faCreditCard, faTruckContainer)

interface OutgoingRow {
	id: number | string
	payee_key: string | null
	counterparty_name: string | null
	organisation_slug?: string
	organisation_code: string | null
	booked_at: string
	reference: string | null
	currency_code: string
	org_amount: number | null
	org_currency_code: string
	lines?: number
	bank?: string
	amount?: number
	fee_amount?: number | null
	payee_source?: string | null
	kind?: string
	order_reference?: string | null
	order_slug?: string | null
	shop_slug?: string | null
}

interface LinkOption {
	type: string
	id: number
	label: string
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	canEdit: boolean
	organisationId: number | null
	organisation: string | null
	organisations: { value: string; label: string }[]
	section: string
	summary: { count: number; total: number | null }
	categories: { value: string; label: string; link_types: string[] }[]
	currencyTotals: Record<string, { count: number; total: number }>
	orgCurrency: string
	min: number | null
	max: number | null
	period: "90d" | "1y" | "all" | null
	currency: string | null
	currencies: string[]
	from: string | null
	to: string | null
	data: { data: OutgoingRow[] }
}>()

const locale = useLocaleStore()
const isQueue = computed(() => props.section === "needs_person")

const linkTypeLabels: Record<string, string> = {
	Supplier: ctrans("Supplier"),
	Agent: ctrans("Agent"),
	Organisation: ctrans("Company of the group"),
	Employee: ctrans("Employee"),
}

const selected = ref<OutgoingRow | null>(null)
const category = ref<string | null>(null)
const link = ref<LinkOption | null>(null)
const linkOptions = ref<LinkOption[] | null>(null)
const error = ref<string | null>(null)
const isSaving = ref(false)

const visible = computed({
	get: () => selected.value !== null,
	set: (value: boolean) => {
		if (!value) {
			selected.value = null
		}
	},
})

const linkTypes = computed(() => props.categories.find((option) => option.value === category.value)?.link_types ?? [])
const linkChoices = computed(() =>
	(linkOptions.value ?? []).filter((option) => linkTypes.value.includes(option.type)).map((option) => ({ ...option, key: `${option.type}:${option.id}`, kind: linkTypeLabels[option.type] }))
)

const open = async (row: OutgoingRow) => {
	selected.value = row
	category.value = props.categories.some((option) => option.value === props.section) ? props.section : null
	link.value = null
	error.value = null
	if (linkOptions.value === null && props.organisationId) {
		try {
			linkOptions.value = (await axios.get(route("grp.json.bank_payee.link_options", [props.organisationId]))).data
		} catch {
			linkOptions.value = []
		}
	}
}

const pickCategory = () => {
	if (link.value && !linkTypes.value.includes(link.value.type)) {
		link.value = null
	}
}

const submit = () => {
	if (!selected.value?.payee_key || !category.value || !props.organisationId) {
		return
	}
	router.post(
		route("grp.models.org.bank_payee.set", [props.organisationId]),
		{ payee_key: selected.value.payee_key, category: category.value, model_type: link.value?.type ?? null, model_id: link.value?.id ?? null },
		{
			preserveScroll: true,
			onStart: () => (isSaving.value = true),
			onFinish: () => (isSaving.value = false),
			onSuccess: () => (selected.value = null),
			onError: (errors: Record<string, string>) => (error.value = Object.values(errors ?? {})[0] ?? ctrans("Something went wrong")),
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<BankLinesFilters
		:organisation-id="organisationId"
		:organisation="organisation"
		:organisations="organisations"
		:summary="summary"
		:currency-totals="currencyTotals"
		:org-currency="orgCurrency"
		:min="min"
		:max="max"
		:period="period"
		:currency="currency"
		:currencies="currencies"
		:from="from"
		:to="to"
	/>

	<p v-if="isQueue && summary.count" class="px-4 pt-3 text-xs text-gray-500">
		{{ ctrans("One row per payee. Say once what a payee is and every payment to it, past and future, follows. It is a label only: no payment or supplier invoice changes.") }}
	</p>

	<Table :resource="data" class="mt-5">
		<template #cell(booked_at)="{ item }">
			<span class="whitespace-nowrap">{{ useFormatTime(item.booked_at, { formatTime: "d MMM yyyy" }) }}</span>
		</template>

		<template #cell(organisation_code)="{ item }">
			<Link v-if="item.organisation_slug" :href="route(`grp.org.accounting.bank_outgoing.${section}`, [item.organisation_slug])" class="primaryLink whitespace-nowrap" v-tooltip="ctrans('Open in the organisation, where payees are classified')">
				{{ item.organisation_code }}
			</Link>
			<span v-else class="whitespace-nowrap text-xs text-gray-600">{{ item.organisation_code }}</span>
		</template>

		<template #cell(bank)="{ item }">
			<span class="whitespace-nowrap">{{ item.bank }}</span>
		</template>

		<template #cell(counterparty_name)="{ item }">
			<span :class="isQueue ? 'font-medium' : 'text-xs'">{{ item.counterparty_name ?? "—" }}</span>
		</template>

		<template #cell(lines)="{ item }">
			<span class="tabular-nums">{{ locale.number(item.lines) }}</span>
		</template>

		<template #cell(org_amount)="{ item }">
			<span v-if="item.org_amount !== null" class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.org_currency_code, item.org_amount) }}</span>
			<span v-else class="text-gray-400">—</span>
			<div v-if="item.currency_code !== item.org_currency_code" class="whitespace-nowrap text-[11px] text-gray-400">{{ item.currency_code }}</div>
		</template>

		<template #cell(amount)="{ item }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, item.amount) }}</span>
			<div v-if="item.org_amount !== null" class="whitespace-nowrap text-[11px] tabular-nums text-gray-400">{{ locale.currencyFormat(item.org_currency_code, item.org_amount) }}</div>
			<div v-if="item.fee_amount" class="whitespace-nowrap text-[11px] tabular-nums text-gray-400">{{ ctrans("of it bank fee :fee", { fee: locale.currencyFormat(item.currency_code, item.fee_amount) }) }}</div>
		</template>

		<template #cell(reference)="{ item }">
			<span class="break-all text-xs text-gray-600">{{ item.reference ?? "—" }}</span>
		</template>

		<template #cell(order_reference)="{ item }">
			<Link v-if="item.order_slug && item.shop_slug" :href="route('grp.org.shops.show.ordering.orders.show', [item.organisation_slug, item.shop_slug, item.order_slug])" class="primaryLink whitespace-nowrap">
				{{ item.order_reference }}
			</Link>
			<span v-else class="text-gray-400">—</span>
		</template>

		<template #cell(kind)="{ item }">
			<span class="whitespace-nowrap text-xs text-gray-600">{{ item.kind }}</span>
		</template>

		<template #cell(actions)="{ item }">
			<div class="flex flex-col items-end gap-1 whitespace-nowrap">
				<span v-if="!isQueue && item.payee_key" class="text-xs text-gray-400">
					{{ section === "customer_refunds" && item.order_reference ? ctrans("Its reference names this order") : item.payee_source === "person" ? ctrans("Classified by a person") : item.payee_source === "auto" ? ctrans("Same name as in Aiku") : ctrans("Card payments and direct debits go here until classified") }}
				</span>
				<Button v-if="canEdit && item.payee_key && !(section === 'customer_refunds' && item.order_reference)" type="tertiary" size="xs" :label="isQueue ? ctrans('Classify') : ctrans('Change')" @click="open(item)">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-tags" :class="isQueue ? 'text-green-600' : 'text-gray-400'" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Table>

	<Dialog v-model:visible="visible" modal :header="ctrans('What is this payee?')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
		<div v-if="selected" class="space-y-4 text-sm">
			<div class="rounded border border-gray-200 px-3 py-2">
				<div class="font-semibold">{{ selected.counterparty_name }}</div>
				<div v-if="selected.lines" class="tabular-nums text-gray-600">
					{{ ctrans(selected.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(selected.lines) }) }}<template v-if="selected.org_amount !== null"> · {{ locale.currencyFormat(selected.org_currency_code, selected.org_amount) }}</template>
				</div>
				<div v-if="selected.reference" class="break-all text-gray-500">{{ selected.reference }}</div>
			</div>

			<label class="block">
				<span class="mb-1 block font-medium">{{ ctrans("It is") }} <span class="text-red-500">*</span></span>
				<Select v-model="category" :options="categories" optionLabel="label" optionValue="value" fluid :placeholder="ctrans('Choose what this payee is')" @change="pickCategory" />
			</label>

			<label v-if="linkTypes.length" class="block">
				<span class="mb-1 block font-medium">{{ ctrans("Who in Aiku") }} <span class="font-normal text-gray-500">({{ ctrans("optional") }})</span></span>
				<div v-if="linkOptions === null" class="skeleton h-9 rounded" />
				<Select v-else v-model="link" :options="linkChoices" optionLabel="label" dataKey="key" filter showClear fluid :virtualScrollerOptions="{ itemSize: 36 }" :placeholder="ctrans('Search by name')">
					<template #option="{ option }">
						<div class="flex w-full min-w-0 items-baseline gap-2">
							<span class="truncate">{{ option.label }}</span>
							<span class="ml-auto whitespace-nowrap text-xs text-gray-500">{{ option.kind }}</span>
						</div>
					</template>
				</Select>
			</label>

			<p class="text-xs text-gray-500">{{ ctrans("Every payment to this payee, in every organisation of the group, moves to the section you choose. You can change it later.") }}</p>
			<p v-if="error" class="text-xs text-red-600">{{ error }}</p>
		</div>

		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<Button type="primary" full :label="ctrans('Save')" :loading="isSaving" :disabled="!category" @click="submit">
					<template #icon>
						<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>
	</Dialog>
</template>
