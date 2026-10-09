<script setup lang="ts">
import { computed, inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboardCheck, faExclamationTriangle, faBoxOpen, faClipboardList, faChevronRight, faPersonDolly, faTruckContainer, faShip } from "@fal"
import { useFormatTime } from "@/Composables/useFormatTime"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

library.add(faClipboardCheck, faExclamationTriangle, faBoxOpen, faClipboardList, faChevronRight, faPersonDolly, faTruckContainer, faShip)

export interface AgentDashboardPurchaseOrdersData {
	currency: string
	summary: {
		open_count: number
		open_value: number
		unconfirmed: number
		unconfirmed_over_week: number
		unconfirmed_stale: number
		overdue: number
		overdue_value: number
		due_soon: number
		confirmed_without_container: number
		confirmed_without_container_value: number
		confirmation_days: number
		stale_days: number
		routes: Record<"open" | "unconfirmed" | "unconfirmed_over_week" | "unconfirmed_stale" | "overdue" | "confirmed_without_container", routeType>
	}
	attention: {
		id: number
		reference: string
		supplier: string
		organisation: string | null
		agent_order: string | null
		state: string
		state_label: string
		amount: number
		is_overdue: boolean
		days_late: number
		days_waiting: number
		route: routeType
	}[]
	suppliers: {
		id: number
		name: string
		open_count: number
		open_value: number
		unconfirmed: number
		overdue: number
		oldest_unconfirmed_days: number | null
		route: routeType
	}[]
	agent_orders: {
		reference: string
		open_count: number
		open_value: number
		unconfirmed: number
		confirmed: number
		overdue: number
		suppliers: number
		oldest_days: number
		expected_at: string | null
		route: routeType
	}[]
	organisations: { name: string; code: string; open_count: number; open_value: number }[]
	containers: { key: string; label: string; value: number; route: routeType }[]
}

const props = defineProps<{
	data: AgentDashboardPurchaseOrdersData
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number) => locale.currencyFormat(props.data.currency, amount)
const openOrders = (count: number) => (count === 1 ? ctrans("1 open order") : ctrans(":count open orders", { count }))
const days = (count: number) => (count === 1 ? ctrans("1 day") : ctrans(":count days", { count }))
const daysLate = (count: number) => (count === 1 ? ctrans("1 day late") : ctrans(":count days late", { count }))
const late = (count: number) => (count === 1 ? ctrans("1 late") : ctrans(":count late", { count }))
const href = (target: routeType) => route(target.name, target.parameters)

const largestOrganisationValue = computed(() => Math.max(1, ...props.data.organisations.map((organisation) => organisation.open_value)))

const tiles = computed(() => [
	{
		key: "unconfirmed",
		label: ctrans("Waiting for your confirmation"),
		icon: "fal fa-clipboard-check",
		value: props.data.summary.unconfirmed,
		tone: props.data.summary.unconfirmed_stale ? "red" : props.data.summary.unconfirmed_over_week ? "amber" : "neutral",
		detail: props.data.summary.unconfirmed ? null : ctrans("Every order sent to you is confirmed"),
		links: [
			{ count: props.data.summary.unconfirmed_over_week, label: ctrans(":count over :days days", { count: props.data.summary.unconfirmed_over_week, days: props.data.summary.confirmation_days }), route: props.data.summary.routes.unconfirmed_over_week },
			{ count: props.data.summary.unconfirmed_stale, label: ctrans(":count over :days days", { count: props.data.summary.unconfirmed_stale, days: props.data.summary.stale_days }), route: props.data.summary.routes.unconfirmed_stale },
		].filter((link) => link.count > 0),
		route: props.data.summary.routes.unconfirmed,
	},
	{
		key: "overdue",
		label: ctrans("Past the expected date"),
		icon: "fal fa-exclamation-triangle",
		value: props.data.summary.overdue,
		tone: props.data.summary.overdue ? "red" : "neutral",
		detail: props.data.summary.overdue
			? ctrans(":value promised and not delivered, :due_soon more due in 14 days", {
					value: money(props.data.summary.overdue_value),
					due_soon: props.data.summary.due_soon,
				})
			: ctrans(":due_soon orders due in the next 14 days", { due_soon: props.data.summary.due_soon }),
		route: props.data.summary.routes.overdue,
	},
	{
		key: "no_container",
		label: ctrans("Confirmed, not in a container"),
		icon: "fal fa-box-open",
		value: props.data.summary.confirmed_without_container,
		tone: props.data.summary.confirmed_without_container ? "amber" : "neutral",
		detail: props.data.summary.confirmed_without_container
			? ctrans(":value waiting for a shipment to be planned", { value: money(props.data.summary.confirmed_without_container_value) })
			: ctrans("Every confirmed order has its container"),
		route: props.data.summary.routes.confirmed_without_container,
	},
	{
		key: "open",
		label: ctrans("Open orders"),
		icon: "fal fa-clipboard-list",
		value: props.data.summary.open_count,
		tone: "neutral",
		detail: money(props.data.summary.open_value),
		route: props.data.summary.routes.open,
	},
])

const toneClasses: Record<string, string> = {
	red: "bg-red-50 text-red-600",
	amber: "bg-amber-50 text-amber-600",
	neutral: "bg-[--app-accent-soft] text-[--app-accent]",
}
</script>

<template>
	<div class="space-y-4">
		<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
			<article
				v-for="tile in tiles"
				:key="tile.key"
				class="group flex flex-col rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
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
					<div class="mt-3 text-3xl font-semibold tracking-tight tabular-nums" :class="tile.tone === 'red' ? 'text-red-600' : 'text-gray-800'">
						{{ locale.number(tile.value) }}
					</div>
				</Link>
				<p v-if="tile.detail" class="mt-1 text-xs leading-relaxed text-gray-500">{{ tile.detail }}</p>
				<div v-if="tile.links?.length" class="mt-1 flex flex-wrap gap-x-3 text-xs">
					<Link v-for="link in tile.links" :key="link.label" :href="href(link.route)" class="text-gray-600 underline decoration-gray-300 underline-offset-2 hover:text-gray-900 hover:decoration-gray-600">
						{{ link.label }}
					</Link>
				</div>
			</article>
		</div>

		<div class="flex flex-wrap items-center gap-x-2 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
			<span class="mr-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
				<FontAwesomeIcon icon="fal fa-ship" class="text-gray-400" fixed-width aria-hidden="true" />
				{{ ctrans("Containers") }}
			</span>
			<template v-for="(step, index) in data.containers" :key="step.key">
				<FontAwesomeIcon v-if="index" icon="fal fa-chevron-right" class="text-xs text-gray-300" fixed-width aria-hidden="true" />
				<Link :href="href(step.route)" class="flex items-center gap-2 rounded px-2 py-1 text-sm transition hover:bg-gray-50">
					<span class="text-lg font-semibold tabular-nums" :class="step.value ? 'text-gray-900' : 'text-gray-300'">{{ locale.number(step.value) }}</span>
					<span :class="step.value ? 'text-gray-600' : 'text-gray-400'">{{ step.label }}</span>
				</Link>
			</template>
		</div>

		<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
			<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm lg:col-span-2">
				<header class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Needs your attention") }}</h2>
					<Link :href="href(data.summary.routes.open)" class="text-xs text-gray-500 hover:text-gray-900 hover:underline">
						{{ ctrans("All open orders, oldest first") }}
					</Link>
				</header>
				<p v-if="!data.attention.length" class="px-4 py-8 text-center text-sm text-gray-400">
					{{ ctrans("Nothing late and nothing waiting longer than :days days", { days: data.summary.confirmation_days }) }}
				</p>
				<ul v-else class="divide-y divide-gray-100">
					<li v-for="purchaseOrder in data.attention" :key="purchaseOrder.id" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50">
						<span
							class="w-28 shrink-0 rounded px-2 py-0.5 text-center text-xs font-medium"
							:class="purchaseOrder.is_overdue ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'">
							{{ purchaseOrder.is_overdue ? daysLate(purchaseOrder.days_late) : ctrans("Waiting :days", { days: days(purchaseOrder.days_waiting) }) }}
						</span>
						<div class="min-w-0 flex-1">
							<Link :href="href(purchaseOrder.route)" class="block truncate text-sm font-medium text-gray-900 hover:underline">
								{{ purchaseOrder.reference }}
							</Link>
							<p class="truncate text-xs text-gray-500">
								{{ purchaseOrder.supplier }}
								<span v-if="purchaseOrder.organisation"> · {{ purchaseOrder.organisation }}</span>
								<span v-if="purchaseOrder.agent_order"> · {{ purchaseOrder.agent_order }}</span>
							</p>
						</div>
						<span class="hidden text-xs text-gray-500 sm:block">{{ purchaseOrder.state_label }}</span>
						<span class="w-32 shrink-0 text-right text-sm tabular-nums text-gray-700">{{ money(purchaseOrder.amount) }}</span>
					</li>
				</ul>
			</section>

			<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
				<header class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
					<FontAwesomeIcon icon="fal fa-person-dolly" class="text-gray-400" fixed-width aria-hidden="true" />
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Sub-suppliers with most open value") }}</h2>
				</header>
				<p v-if="!data.suppliers.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("No open orders") }}</p>
				<ul v-else class="divide-y divide-gray-100">
					<li v-for="supplier in data.suppliers" :key="supplier.id" class="px-4 py-2.5 hover:bg-gray-50">
						<div class="flex items-center justify-between gap-2">
							<Link :href="href(supplier.route)" class="truncate text-sm font-medium text-gray-900 hover:underline">{{ supplier.name }}</Link>
							<span class="shrink-0 text-sm tabular-nums text-gray-700">{{ money(supplier.open_value) }}</span>
						</div>
						<div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
							<span>{{ openOrders(supplier.open_count) }}</span>
							<span v-if="supplier.overdue" class="rounded bg-red-50 px-1.5 text-red-700">{{ late(supplier.overdue) }}</span>
							<span v-if="supplier.oldest_unconfirmed_days !== null" class="rounded bg-amber-50 px-1.5 text-amber-700">
								{{ ctrans(":count unconfirmed, oldest :days", { count: supplier.unconfirmed, days: days(supplier.oldest_unconfirmed_days) }) }}
							</span>
						</div>
					</li>
				</ul>

				<div v-if="data.organisations.length" class="border-t border-gray-200 px-4 py-3">
					<h3 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Open value by organisation") }}</h3>
					<ul class="mt-2 space-y-1.5">
						<li v-for="organisation in data.organisations" :key="organisation.code" class="text-xs">
							<div class="flex items-center justify-between gap-2 text-gray-600">
								<span class="truncate">{{ organisation.name }} <span class="text-gray-400">· {{ organisation.open_count }}</span></span>
								<span class="shrink-0 tabular-nums text-gray-700">{{ money(organisation.open_value) }}</span>
							</div>
							<div class="mt-0.5 h-1.5 w-full rounded-full bg-gray-100">
								<div class="h-1.5 rounded-full bg-[--app-accent]" :style="{ width: `${Math.max(2, (organisation.open_value / largestOrganisationValue) * 100)}%` }" />
							</div>
						</li>
					</ul>
				</div>
			</section>
		</div>

		<section v-if="data.agent_orders.length" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<header class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
				<FontAwesomeIcon icon="fal fa-truck-container" class="text-gray-400" fixed-width aria-hidden="true" />
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Agent orders still open, oldest first") }}</h2>
			</header>
			<table class="w-full text-sm">
				<thead class="bg-gray-50 text-xs text-gray-500">
					<tr>
						<th class="px-4 py-2 text-left font-medium">{{ ctrans("Agent order") }}</th>
						<th class="px-4 py-2 text-right font-medium">{{ ctrans("Sub-suppliers") }}</th>
						<th class="px-4 py-2 text-right font-medium">{{ ctrans("Unconfirmed") }}</th>
						<th class="px-4 py-2 text-right font-medium">{{ ctrans("Confirmed") }}</th>
						<th class="hidden px-4 py-2 text-right font-medium sm:table-cell">{{ ctrans("Late") }}</th>
						<th class="px-4 py-2 text-right font-medium">{{ ctrans("Open value") }}</th>
						<th class="hidden px-4 py-2 text-right font-medium md:table-cell">{{ ctrans("Oldest") }}</th>
						<th class="hidden px-4 py-2 text-right font-medium md:table-cell">{{ ctrans("Expected") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="agentOrder in data.agent_orders" :key="agentOrder.reference" class="hover:bg-gray-50">
						<td class="px-4 py-2 font-medium">
							<Link :href="href(agentOrder.route)" class="text-gray-900 hover:underline">{{ agentOrder.reference }}</Link>
						</td>
						<td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ agentOrder.suppliers }}</td>
						<td class="px-4 py-2 text-right tabular-nums" :class="agentOrder.unconfirmed ? 'text-amber-700' : 'text-gray-400'">{{ agentOrder.unconfirmed }}</td>
						<td class="px-4 py-2 text-right tabular-nums" :class="agentOrder.confirmed ? 'text-gray-700' : 'text-gray-400'">{{ agentOrder.confirmed }}</td>
						<td class="hidden px-4 py-2 text-right tabular-nums sm:table-cell" :class="agentOrder.overdue ? 'font-semibold text-red-600' : 'text-gray-400'">{{ agentOrder.overdue }}</td>
						<td class="px-4 py-2 text-right tabular-nums text-gray-700">{{ money(agentOrder.open_value) }}</td>
						<td class="hidden px-4 py-2 text-right tabular-nums text-gray-500 md:table-cell">{{ days(agentOrder.oldest_days) }}</td>
						<td class="hidden px-4 py-2 text-right text-gray-500 md:table-cell">{{ agentOrder.expected_at ? useFormatTime(agentOrder.expected_at) : "—" }}</td>
					</tr>
				</tbody>
			</table>
		</section>
	</div>
</template>
