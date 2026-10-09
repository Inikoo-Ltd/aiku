<script setup lang="ts">
import { computed, inject } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faHandHoldingUsd, faMoneyCheckAlt, faCheckCircle, faChevronRight } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentDepositsEmptyState from "@/Components/Procurement/AgentDepositsEmptyState.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faHandHoldingUsd, faMoneyCheckAlt, faCheckCircle, faChevronRight)

interface Money {
	currency_code: string
	amount: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	routes: { deposits: routeType; deposit_requests: routeType }
	data: {
		summary: {
			pending_deposits: number
			pending_deposits_amount: Money[]
			open_requests: number
			open_requests_amount: Money[]
			settled_requests: number
		}
		pending_by_organisation: { id: number; name: string; code: string; currency_code: string; number_deposits: number; amount: number }[]
		open_requests: { id: number; reference: string | null; currency_code: string; requested_at: string; number_items: number; total: number; outstanding: number }[]
		recently_settled: { id: number; reference: string | null; currency_code: string; settled_at: string; total: number }[]
		recently_paid: { id: number; reference: string | null; currency_code: string; amount: number; paid_to_supplier_at: string; purchase_order: string | null; organisation: string | null }[]
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (code: string, amount: number) => locale.currencyFormat(code, amount)
const moneyList = (amounts: Money[]) => (amounts.length ? amounts.map((entry) => money(entry.currency_code, entry.amount)).join(" · ") : null)
const href = (target: routeType) => route(target.name, target.parameters)
const requestName = (request: { reference: string | null; id: number }) => request.reference || ctrans("Request #:id", { id: request.id })

const isEmpty = computed(
	() =>
		!props.data.summary.pending_deposits &&
		!props.data.summary.open_requests &&
		!props.data.summary.settled_requests &&
		!props.data.recently_paid.length
)

const tiles = computed(() => [
	{
		key: "pending",
		label: ctrans("Pending deposits"),
		icon: "fal fa-hand-holding-usd",
		value: props.data.summary.pending_deposits,
		tone: props.data.summary.pending_deposits ? "amber" : "neutral",
		detail: moneyList(props.data.summary.pending_deposits_amount) ?? ctrans("Nothing paid out and waiting to be claimed"),
		route: { name: props.routes.deposits.name, parameters: { ...(props.routes.deposits.parameters as object), _query: { state: "pending" } } },
	},
	{
		key: "open",
		label: ctrans("Open deposit requests"),
		icon: "fal fa-money-check-alt",
		value: props.data.summary.open_requests,
		tone: props.data.summary.open_requests ? "amber" : "neutral",
		detail: moneyList(props.data.summary.open_requests_amount)
			? ctrans(":value still to be reimbursed", { value: moneyList(props.data.summary.open_requests_amount) })
			: ctrans("No reimbursement outstanding"),
		route: { name: props.routes.deposit_requests.name, parameters: { ...(props.routes.deposit_requests.parameters as object), _query: { state: "requested" } } },
	},
	{
		key: "settled",
		label: ctrans("Settled requests"),
		icon: "fal fa-check-circle",
		value: props.data.summary.settled_requests,
		tone: props.data.summary.settled_requests ? "green" : "neutral",
		detail: ctrans("Fully reimbursed, all time"),
		route: { name: props.routes.deposit_requests.name, parameters: { ...(props.routes.deposit_requests.parameters as object), _query: { state: "settled" } } },
	},
])

const toneClasses: Record<string, string> = {
	amber: "bg-amber-50 text-amber-600",
	green: "bg-green-50 text-green-600",
	neutral: "bg-[--app-accent-soft] text-[--app-accent]",
}
</script>

<template>
	<div>
		<Head :title="capitalize(title)" />
		<PageHeading :data="pageHead" />

		<div class="mx-4 mt-4 space-y-4">
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
				<article v-for="tile in tiles" :key="tile.key" class="group rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
					<Link :href="href(tile.route)" class="block">
						<div class="flex items-center justify-between gap-3">
							<div class="flex min-w-0 items-center gap-2.5">
								<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :class="toneClasses[tile.tone]">
									<FontAwesomeIcon :icon="tile.icon" class="text-sm" fixed-width aria-hidden="true" />
								</span>
								<h2 class="truncate text-sm font-semibold text-gray-600">{{ tile.label }}</h2>
							</div>
							<FontAwesomeIcon icon="fal fa-chevron-right" class="text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" fixed-width aria-hidden="true" />
						</div>
						<div class="mt-3 text-3xl font-semibold tracking-tight tabular-nums text-gray-800">{{ locale.number(tile.value) }}</div>
					</Link>
					<p class="mt-1 text-xs leading-relaxed text-gray-500">{{ tile.detail }}</p>
				</article>
			</div>

			<div v-if="isEmpty" class="rounded-lg border border-gray-200 bg-white shadow-sm">
				<AgentDepositsEmptyState :title="ctrans('No deposits recorded yet')" />
			</div>

			<div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
				<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Owed to you, by organisation") }}</h2>
						<Link :href="href(routes.deposits)" class="text-xs text-gray-500 hover:text-gray-900 hover:underline">{{ ctrans("All deposits") }}</Link>
					</header>
					<p v-if="!data.pending_by_organisation.length" class="px-4 py-8 text-center text-sm text-gray-400">
						{{ ctrans("Every deposit you paid has been claimed in a deposit request") }}
					</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="organisation in data.pending_by_organisation" :key="`${organisation.id}-${organisation.currency_code}`" class="flex items-center justify-between gap-3 px-4 py-2.5">
							<div class="min-w-0">
								<div class="truncate text-sm font-medium text-gray-900">{{ organisation.name }}</div>
								<div class="text-xs text-gray-500">
									{{ organisation.number_deposits === 1 ? ctrans("1 pending deposit") : ctrans(":count pending deposits", { count: organisation.number_deposits }) }}
								</div>
							</div>
							<span class="text-sm font-semibold tabular-nums text-gray-800">{{ money(organisation.currency_code, organisation.amount) }}</span>
						</li>
					</ul>
				</section>

				<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Open deposit requests") }}</h2>
						<Link :href="href(routes.deposit_requests)" class="text-xs text-gray-500 hover:text-gray-900 hover:underline">{{ ctrans("All requests") }}</Link>
					</header>
					<p v-if="!data.open_requests.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No request is waiting to be reimbursed") }}</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="request in data.open_requests" :key="request.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
							<div class="min-w-0">
								<div class="truncate text-sm font-medium text-gray-900">{{ requestName(request) }}</div>
								<div class="text-xs text-gray-500">
									{{ ctrans("Requested :date, :items items", { date: useFormatTime(request.requested_at), items: request.number_items }) }}
								</div>
							</div>
							<div class="text-right">
								<div class="text-sm font-semibold tabular-nums text-amber-600">{{ money(request.currency_code, request.outstanding) }}</div>
								<div v-if="request.outstanding !== request.total" class="text-xs tabular-nums text-gray-400">{{ ctrans("of :total", { total: money(request.currency_code, request.total) }) }}</div>
							</div>
						</li>
					</ul>
				</section>

				<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="border-b border-gray-200 px-4 py-3">
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Recently settled") }}</h2>
					</header>
					<p v-if="!data.recently_settled.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No request has been settled yet") }}</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="request in data.recently_settled" :key="request.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
							<div class="min-w-0">
								<div class="truncate text-sm font-medium text-gray-900">{{ requestName(request) }}</div>
								<div class="text-xs text-gray-500">{{ useFormatTime(request.settled_at) }}</div>
							</div>
							<span class="text-sm font-semibold tabular-nums text-green-600">{{ money(request.currency_code, request.total) }}</span>
						</li>
					</ul>
				</section>

				<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="border-b border-gray-200 px-4 py-3">
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Recently paid to suppliers") }}</h2>
					</header>
					<p v-if="!data.recently_paid.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No deposit has been marked as paid to a supplier yet") }}</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="deposit in data.recently_paid" :key="deposit.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
							<div class="min-w-0">
								<div class="truncate text-sm font-medium text-gray-900">{{ deposit.reference || deposit.purchase_order || ctrans("Deposit #:id", { id: deposit.id }) }}</div>
								<div class="truncate text-xs text-gray-500">{{ [deposit.organisation, useFormatTime(deposit.paid_to_supplier_at)].filter(Boolean).join(" · ") }}</div>
							</div>
							<span class="text-sm font-semibold tabular-nums text-gray-800">{{ money(deposit.currency_code, deposit.amount) }}</span>
						</li>
					</ul>
				</section>
			</div>
		</div>
	</div>
</template>
