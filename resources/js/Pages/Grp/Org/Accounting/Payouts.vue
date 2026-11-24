<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 16 Nov 2026 11:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed } from "vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHandHoldingBox, faCreditCard, faStore, faExclamationTriangle, faEyeSlash, faListUl } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faHandHoldingBox, faCreditCard, faStore, faExclamationTriangle, faEyeSlash, faListUl)

type HeldStatus = "known" | "not_seen" | "not_read"
interface Figure {
	native: number
	amount: number
	count?: number
}
interface Row {
	key: string
	kind: "provider" | "marketplace"
	provider: string
	label: string
	shop_code?: string | null
	organisation_code: string | null
	currency: string | null
	paid: Figure
	refunded: Figure
	paid_out: Figure
	channels: { key: string; label: string; native: number; count: number }[]
	fees: Figure & { unknown: number }
	held: { status: HeldStatus; native: number | null; amount: number | null; count: number; oldest: string | null; stale: { count: number; native: number; amount: number } }
	not_reconciled: { count: number; amount: number }
	route: { name: string; parameters: Record<string, string> } | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	currency: string
	payouts: {
		period: string
		held_days: number
		rows: Row[]
		totals: {
			paid: number
			refunded: number
			paid_out: number
			fees: number
			fees_unknown: number
			held: number
			held_stale: number
			held_count: number
			not_seen: number
			not_seen_unpaid: number
			not_reconciled: { count: number; amount: number }
		}
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const native = (row: Row, amount: number | null) => (amount === null || !row.currency ? "" : locale.currencyFormat(row.currency, amount))

const reload = (query: { period?: string | null; org?: string | null }) => {
	const wanted = { period: props.payouts.period === "90d" ? null : props.payouts.period, org: props.organisation, ...query }
	const parameters: Record<string, string> = props.isGroup ? {} : { organisation: route().params.organisation as string }
	if (wanted.period) {
		parameters.period = wanted.period
	}
	if (props.isGroup && wanted.org && wanted.org !== "all") {
		parameters.org = wanted.org
	}
	router.get(route(props.isGroup ? "grp.financials.payouts.index" : "grp.org.accounting.payouts.index", parameters), {}, { preserveScroll: true })
}

const periodOptions = [
	{ label: ctrans("90 days"), value: "90d" },
	{ label: ctrans("1 year"), value: "1y" },
	{ label: ctrans("All"), value: "all" },
]
const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])

const groups = computed(() => {
	const labels: Record<string, string> = { checkout: "Checkout", paypal: "PayPal", pastpay: "PastPay", cod: ctrans("Cash on delivery"), marketplace: ctrans("Marketplaces") }
	return Object.entries(labels)
		.map(([key, label]) => ({ key, label, rows: props.payouts.rows.filter((row) => row.provider === key) }))
		.filter((group) => group.rows.length)
		.map((group) => ({
			...group,
			paid: group.rows.reduce((sum, row) => sum + row.paid.amount, 0),
			paidOut: group.rows.reduce((sum, row) => sum + row.paid_out.amount, 0),
			fees: group.rows.reduce((sum, row) => sum + row.fees.amount, 0),
			held: group.rows.filter((row) => row.held.status === "known").reduce((sum, row) => sum + (row.held.amount ?? 0), 0),
			notSeen: group.rows.filter((row) => row.held.status !== "known").length,
			notReconciled: group.rows.reduce((sum, row) => sum + row.not_reconciled.count, 0),
		}))
})

const heldNote = (row: Row) => {
	if (row.held.status === "not_seen") {
		return row.held.amount !== null ? ctrans("Payouts never seen in a connected bank: unpaid in Aiku") : ctrans("Payouts never seen in a connected bank")
	}
	if (row.held.status === "not_read") {
		return ctrans("Payouts arrive but none was reconciled yet")
	}
	return row.held.oldest ? ctrans("oldest :ago", { ago: useRangeFromNow(row.held.oldest, { addSuffix: true }) }) : ""
}

const cards = computed(() => [
	{ key: "paid", label: ctrans("Customers paid through them"), amount: props.payouts.totals.paid, note: props.payouts.totals.refunded ? ctrans(":amount refunded", { amount: money(props.payouts.totals.refunded) }) : "" },
	{ key: "paid_out", label: ctrans("Paid out to our banks"), amount: props.payouts.totals.paid_out, note: ctrans("connected banks only") },
	{ key: "fees", label: ctrans("Their fees"), amount: props.payouts.totals.fees, note: props.payouts.totals.fees_unknown ? ctrans(":count payouts with fees not known yet", { count: locale.number(props.payouts.totals.fees_unknown) }) : "" },
	{
		key: "held",
		label: ctrans("Still held today"),
		amount: props.payouts.totals.held,
		note: props.payouts.totals.held_stale
			? ctrans("last :days days; :amount older", { days: props.payouts.held_days, amount: money(props.payouts.totals.held_stale) })
			: ctrans(":count payments or invoices", { count: locale.number(props.payouts.totals.held_count) }),
	},
])

const share = (value: number, total: number) => `${Math.max(2, (Math.max(value, 0) / Math.max(total, 1)) * 100)}%`
const maxHeld = computed(() => Math.max(...groups.value.map((group) => group.held), 1))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<p class="text-xs text-gray-500">{{ ctrans("Paid, paid out and fees are for the period. Still held is as of today.") }}</p>
			<div class="flex flex-wrap gap-2">
				<SegmentedToggle v-if="organisations.length > 1" :model-value="organisation ?? 'all'" :options="organisationOptions" :aria-label="ctrans('Organisation')" @update:model-value="(value: string) => reload({ org: value })" />
				<SegmentedToggle :model-value="payouts.period" :options="periodOptions" :aria-label="ctrans('Period')" @update:model-value="(value: string) => reload({ period: value === '90d' ? null : value })" />
			</div>
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<div class="text-xs text-gray-500">{{ card.label }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(card.amount) }}</div>
				<div v-if="card.note" class="mt-0.5 text-xs text-gray-500">{{ card.note }}</div>
			</div>
		</div>

		<div v-if="payouts.totals.not_reconciled.count || payouts.totals.not_seen" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
			<p v-if="payouts.totals.not_reconciled.count" class="flex items-center gap-2">
				<FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
				{{ ctrans(":count payouts of :amount did not reconcile in this period", { count: locale.number(payouts.totals.not_reconciled.count), amount: money(payouts.totals.not_reconciled.amount) }) }}
			</p>
			<p v-if="payouts.totals.not_seen" class="mt-1 flex items-center gap-2">
				<FontAwesomeIcon icon="fal fa-eye-slash" fixed-width aria-hidden="true" />
				{{ ctrans("What :count rows still hold is not known: their payouts never reached a connected bank, or none was reconciled", { count: locale.number(payouts.totals.not_seen) }) }}
				<template v-if="payouts.totals.not_seen_unpaid">{{ ctrans("(:amount unpaid in Aiku)", { amount: money(payouts.totals.not_seen_unpaid) }) }}</template>
			</p>
		</div>

		<div v-if="!payouts.rows.length" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center">
			<div class="text-lg font-light text-gray-600">{{ ctrans("No payment through a provider or marketplace in this period") }}</div>
		</div>

		<template v-else>
			<DashboardWidgetBox storageKey="payouts_held_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-hand-holding-box" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("By provider") }}
					</span>
					<span class="text-xs text-gray-400">{{ currency }}</span>
				</template>
				<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
					<div v-for="group in groups" :key="group.key" class="text-sm tabular-nums">
						<div class="flex items-center gap-2 font-medium text-gray-700">
							<FontAwesomeIcon :icon="group.key === 'marketplace' ? 'fal fa-store' : 'fal fa-credit-card'" class="text-gray-400" fixed-width aria-hidden="true" />
							{{ group.label }}
						</div>
						<dl class="mt-1 grid grid-cols-2 gap-x-3 text-xs">
							<dt class="text-gray-500">{{ ctrans("Paid") }}</dt>
							<dd class="text-right">{{ money(group.paid) }}</dd>
							<dt class="text-gray-500">{{ ctrans("Paid out") }}</dt>
							<dd class="text-right">{{ money(group.paidOut) }}</dd>
							<dt class="text-gray-500">{{ ctrans("Fees") }}</dt>
							<dd class="text-right">{{ money(group.fees) }}</dd>
							<dt class="font-medium text-gray-700">{{ ctrans("Held") }}</dt>
							<dd class="text-right font-medium">{{ money(group.held) }}</dd>
						</dl>
						<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
							<div class="h-1.5 rounded-full bg-[var(--app-accent)]" :style="{ width: share(group.held, maxHeld) }" />
						</div>
						<div v-if="group.notSeen || group.notReconciled" class="mt-1 text-xs text-amber-700">
							<span v-if="group.notSeen">{{ ctrans(":count not seen", { count: group.notSeen }) }}</span>
							<span v-if="group.notSeen && group.notReconciled"> · </span>
							<span v-if="group.notReconciled">{{ ctrans(":count not reconciled", { count: group.notReconciled }) }}</span>
						</div>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="payouts_rows_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Per provider and currency") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("in the currency paid, :currency in grey", { currency }) }}</span>
				</template>
				<div class="overflow-x-auto">
					<table class="w-full text-sm tabular-nums">
						<thead class="whitespace-nowrap text-left text-xs uppercase text-gray-500">
							<tr>
								<th class="py-1.5 pr-4 font-medium">{{ ctrans("Provider") }}</th>
								<th v-if="isGroup" class="py-1.5 pr-4 font-medium">{{ ctrans("Organisation") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Customers paid") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Paid out") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Fees") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Still held") }}</th>
								<th class="py-1.5 font-medium">{{ ctrans("Did not reconcile") }}</th>
							</tr>
						</thead>
						<tbody v-for="group in groups" :key="group.key" class="border-t border-gray-200">
							<tr v-for="row in group.rows" :key="row.key" class="border-t border-gray-100 align-top">
								<td class="py-1.5 pr-4">
									<div class="font-medium">
										{{ row.label }}
										<span v-if="row.currency" class="ml-1 whitespace-nowrap text-xs font-normal text-gray-500">{{ row.currency }}</span>
									</div>
									<div v-if="row.channels.length" class="text-xs text-gray-500">{{ row.channels.map((channel) => channel.label).join(", ") }}</div>
								</td>
								<td v-if="isGroup" class="whitespace-nowrap py-1.5 pr-4 text-gray-700">{{ row.organisation_code }}</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right">
									<div>{{ native(row, row.paid.native) }}</div>
									<div class="text-xs text-gray-500">
										{{ money(row.paid.amount) }}
										<template v-if="row.refunded.native"> · {{ ctrans(":amount refunded", { amount: native(row, row.refunded.native) }) }}</template>
									</div>
								</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right">
									<template v-if="row.paid_out.count">
										<div>{{ native(row, row.paid_out.native) }}</div>
										<div class="text-xs text-gray-500">{{ ctrans(":count payouts", { count: locale.number(row.paid_out.count) }) }}</div>
									</template>
									<span v-else class="text-gray-400">—</span>
								</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right">
									<template v-if="row.paid_out.count">
										<div>{{ native(row, row.fees.native) }}</div>
										<div v-if="row.fees.unknown" class="text-xs text-amber-700">{{ ctrans(":count not known yet", { count: locale.number(row.fees.unknown) }) }}</div>
									</template>
									<span v-else class="text-gray-400">—</span>
								</td>
								<td class="py-1.5 pr-4 text-right">
									<template v-if="row.held.status === 'known'">
										<div class="whitespace-nowrap font-medium">{{ native(row, row.held.native) }}</div>
										<div class="whitespace-nowrap text-xs text-gray-500">{{ heldNote(row) }}</div>
										<div v-if="row.held.stale.count" class="whitespace-nowrap text-xs text-amber-700">
											{{ ctrans("+ :amount older than :days days", { amount: native(row, row.held.stale.native), days: payouts.held_days }) }}
										</div>
									</template>
									<template v-else>
										<div v-if="row.held.native !== null && row.held.native + row.held.stale.native" class="whitespace-nowrap text-gray-500">{{ native(row, row.held.native + row.held.stale.native) }}</div>
										<div class="text-xs text-amber-700">{{ heldNote(row) }}</div>
									</template>
								</td>
								<td class="whitespace-nowrap py-1.5">
									<template v-if="row.not_reconciled.count">
										<Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="text-amber-700 underline decoration-dotted hover:text-amber-900">
											{{ ctrans(":count of :amount", { count: locale.number(row.not_reconciled.count), amount: money(row.not_reconciled.amount) }) }}
										</Link>
										<span v-else class="text-amber-700">{{ locale.number(row.not_reconciled.count) }}</span>
									</template>
									<span v-else-if="row.paid_out.count" class="text-xs text-green-700">{{ ctrans("all reconciled") }}</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p class="mt-3 text-xs text-gray-500">
					{{ ctrans("Held: Checkout and PastPay payments no reconciled payout has settled yet; unpaid cash on delivery invoices; unpaid marketplace invoices.") }}
					{{ ctrans("Money waiting longer than :days days is shown apart and left out of the total: it was most likely paid in a payout that did not reconcile.", { days: payouts.held_days }) }}
				</p>
			</DashboardWidgetBox>
		</template>
	</div>
</template>
