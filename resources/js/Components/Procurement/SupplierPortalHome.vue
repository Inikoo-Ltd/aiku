<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-09 15:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierPortalStatCard from "@/Components/Procurement/SupplierPortalStatCard.vue"
import type { JourneyRibbon } from "@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue"
import Image from "@common/Components/Image.vue"
import type { Image as ImageProxy } from "@/types/Image"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowUp, faArrowDown, faBox, faBuilding, faChevronRight, faClipboard, faEnvelope, faEnvelopeOpenText, faExclamationTriangle, faCheckCircle, faClock, faMapMarkerAlt, faPhone, faStopwatch, faTrophy, faUsers, faUserTie } from "@fal"

library.add(faArrowUp, faArrowDown, faBox, faBuilding, faChevronRight, faClipboard, faEnvelope, faEnvelopeOpenText, faExclamationTriangle, faCheckCircle, faClock, faMapMarkerAlt, faPhone, faStopwatch, faTrophy, faUsers, faUserTie)

interface Person {
	contact_name: string | null
	email: string | null
	phone: string | null
}

export interface PortalSummary {
	currency: string | null
	from: string
	to: string
	previous_from: string
	previous_to: string
	as_of: string
	sales: number
	previous: number
	change: number | null
	country: { code: string; name: string } | null
	ranking: { position: number; of: number } | null
	production: { target: number; start: string; average: number | null; orders: number; minimum: number; position: number | null; of: number } | null
}

export interface PortalTheme {
	background: string
	text: string
	soft: string
	badge: string
}

const props = defineProps<{
	supplier: { code: string; name: string }
	group: string
	logo?: ImageProxy | null
	company?: Person & { name: string; location: string | null; currency: string | null }
	agent?: (Person & { name: string }) | null
	buyers?: { organisation: string; name: string | null }[]
	summary?: PortalSummary
	sales?: { products: number; selling: number }
	openOrders: number
	orders?: { ribbons: JourneyRibbon[] }
	theme: PortalTheme
	accent: { colour: string; tint: string }
}>()

const emit = defineEmits<{
	go: [tab: "products" | "purchase_orders"]
	open: [purchaseOrderId: number]
}>()

const locale = useLocaleStore()

const SHOWN_ORDERS = 5

const money = (amount: number, currency: string | null) =>
	currency ? new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 0 }).format(Math.round(amount)) : Math.round(amount).toString()

const changeLabel = (change: number) =>
	change >= 0 ? ctrans(":percent% more than the 12 months before", { percent: String(change) }) : ctrans(":percent% less than the 12 months before", { percent: String(Math.abs(change)) })

const initials = (name: string) =>
	name
		.split(/\s+/)
		.filter(Boolean)
		.slice(0, 2)
		.map((word) => word.charAt(0))
		.join("")
		.toUpperCase()

const welcome = computed(() => [
	ctrans("We believe good business starts with trust, honesty and straightforward conversations. We take responsibility for our part, respect the people who make our products, and bring generosity, good humour and care to the work we share. We welcome your ideas and want to build a lasting, successful partnership."),
	ctrans("Thank you for your care and attention to products supplied to :group. Together we can sell more of your products.", { group: props.group }),
])

const openRibbons = computed(() => (props.orders?.ribbons ?? []).filter((ribbon) => ribbon.status !== "completed"))
const needsAttention = computed(() => openRibbons.value.filter((ribbon) => ribbon.status === "overdue" || ribbon.status === "at_risk").length)
const shownRibbons = computed(() => openRibbons.value.slice(0, SHOWN_ORDERS))
const hiddenRibbons = computed(() => Math.max(openRibbons.value.length - SHOWN_ORDERS, 0))

const statusPill = (ribbon: JourneyRibbon): { label: string; class: string; icon: string } => {
	if (ribbon.status === "overdue") {
		return { label: ctrans(":days days overdue", { days: String(ribbon.days_overdue) }), class: "bg-red-50 text-red-700", icon: "fal fa-exclamation-triangle" }
	}
	if (ribbon.status === "at_risk") {
		return { label: ctrans("At risk"), class: "bg-amber-50 text-amber-700", icon: "fal fa-clock" }
	}
	return { label: ctrans("On track"), class: "bg-green-50 text-green-700", icon: "fal fa-check-circle" }
}

const orderAmount = (ribbon: JourneyRibbon): string => (ribbon.amount !== null && ribbon.currency_code ? String(locale.currencyFormat(ribbon.currency_code, ribbon.amount)) : "")

const productionGap = (production: NonNullable<PortalSummary["production"]>, average: number) => String(Math.round(Math.abs(average - production.target) * 10) / 10)
</script>

<template>
	<div class="flex flex-col gap-4 lg:gap-6">
		<section class="relative overflow-hidden rounded-xl shadow-sm" :style="{ backgroundColor: theme.background, color: theme.text }">
			<span class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full" :style="{ backgroundColor: theme.badge }" aria-hidden="true" />
			<span class="pointer-events-none absolute -bottom-24 right-40 h-48 w-48 rounded-full opacity-60" :style="{ backgroundColor: theme.badge }" aria-hidden="true" />

			<div class="relative flex flex-col gap-6 p-5 sm:p-7 md:flex-row md:items-center md:justify-between">
				<div class="flex min-w-0 items-center gap-4">
					<div class="relative flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-xl text-lg font-bold" :style="{ backgroundColor: theme.badge }">
						<Image v-if="logo" :src="logo" class="absolute inset-0 h-full w-full object-cover" :alt="supplier.name" />
						<template v-else>{{ initials(supplier.name) }}</template>
					</div>
					<div class="min-w-0">
						<p class="text-xs font-medium uppercase tracking-wider opacity-75">{{ ctrans("Welcome") }}</p>
						<h2 class="truncate text-2xl font-semibold leading-tight">{{ supplier.name }}</h2>
						<p class="mt-0.5 text-sm opacity-80">{{ ctrans("Your supplier page with :group", { group }) }}</p>
					</div>
				</div>

				<div class="w-full rounded-lg p-4 backdrop-blur-sm md:w-80 md:shrink-0" :style="{ backgroundColor: theme.badge }">
					<p class="text-xs font-medium uppercase tracking-wide opacity-80">{{ ctrans("Sales in the last 12 months") }}</p>
					<div v-if="!summary" class="skeleton mt-2 h-12 rounded" />
					<template v-else>
						<p class="mt-1 text-3xl font-semibold tabular-nums">{{ money(summary.sales, summary.currency) }}</p>
						<p
							v-if="summary.change !== null"
							class="mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
							:class="summary.change >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
							<FontAwesomeIcon :icon="summary.change >= 0 ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width aria-hidden="true" />
							{{ changeLabel(summary.change) }}
						</p>
						<p v-else class="mt-2 text-xs opacity-80">{{ ctrans("No sales in the 12 months before to compare with") }}</p>
					</template>
				</div>
			</div>

			<p v-if="summary" class="relative border-t px-5 py-2.5 text-xs opacity-75 sm:px-7" :style="{ borderColor: theme.badge }">
				{{ ctrans("At your prices, UK, Spain and Slovakia together, :from to :to. Updated :date.", { from: useFormatTime(summary.from), to: useFormatTime(summary.to), date: useFormatTime(summary.as_of) }) }}
			</p>
		</section>

		<article class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
			<header class="flex items-center gap-3 border-b border-gray-100 bg-white px-5 py-3 sm:px-6">
				<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold" :style="{ backgroundColor: theme.background, color: theme.text }">
					{{ initials(group) }}
				</div>
				<div class="min-w-0 flex-1">
					<p class="truncate text-sm font-semibold text-gray-800">{{ ctrans("A message from :group", { group }) }}</p>
					<p class="truncate text-xs text-gray-500">{{ ctrans("We are pleased to be your distribution partner in Europe.") }}</p>
				</div>
				<FontAwesomeIcon icon="fal fa-envelope-open-text" class="hidden text-xl sm:block" :style="{ color: accent.colour }" aria-hidden="true" />
			</header>
			<div class="px-5 py-5 sm:px-6" :style="{ backgroundColor: theme.soft }">
				<div class="flex max-w-3xl flex-col gap-3 text-sm leading-relaxed text-gray-700">
					<p class="font-semibold text-gray-900">{{ ctrans("Dear :supplier,", { supplier: supplier.name }) }}</p>
					<p>{{ ctrans("Thank you for trading with :group.", { group }) }}</p>
					<p>{{ welcome[0] }}</p>
					<p class="border-l-2 pl-3 font-medium text-gray-900" :style="{ borderColor: theme.background }">{{ welcome[1] }}</p>
				</div>
				<footer class="mt-5 text-sm">
					<p class="text-gray-500">{{ ctrans("With warm regards,") }}</p>
					<p class="mt-1 font-serif text-base italic text-gray-900">{{ ctrans("The :group team", { group }) }}</p>
				</footer>
			</div>
		</article>

		<section class="grid gap-4 md:grid-cols-3">
			<SupplierPortalStatCard
				:label="summary?.country ? ctrans('Ranking by sales in :country', { country: summary.country.name }) : ctrans('Ranking by sales')"
				icon="fal fa-trophy"
				:accent="accent"
				:loading="!summary">
				<template v-if="summary">
					<template v-if="summary.ranking">
						<p class="text-2xl font-semibold tabular-nums text-gray-900">#{{ summary.ranking.position }}</p>
						<p class="mt-1.5 text-xs text-gray-600">{{ ctrans(":position of :count suppliers in :country", { position: String(summary.ranking.position), count: String(summary.ranking.of), country: summary.country?.name ?? "" }) }}</p>
					</template>
					<p v-else class="text-sm text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
				</template>
				<template v-if="summary" #note>
					{{ ctrans("Against every :group supplier based in your country with sales in the same 12 months, with an agent or without, each at its own prices. Other suppliers are never named.", { group }) }}
				</template>
			</SupplierPortalStatCard>

			<SupplierPortalStatCard
				:label="summary?.country ? ctrans('Production time to CHO in :country', { country: summary.country.name }) : ctrans('Production time to CHO')"
				icon="fal fa-stopwatch"
				:accent="accent"
				:loading="!summary?.production">
				<template v-if="summary?.production">
					<template v-if="summary.production.average !== null">
						<p class="text-2xl font-semibold tabular-nums text-gray-900">{{ ctrans(":days days", { days: String(summary.production.average) }) }}</p>
						<p
							class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
							:class="summary.production.average > summary.production.target ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'">
							{{
								summary.production.average > summary.production.target
									? ctrans("+:days days above the :target day target", { days: productionGap(summary.production, summary.production.average), target: String(summary.production.target) })
									: ctrans(":days days below the :target day target", { days: productionGap(summary.production, summary.production.average), target: String(summary.production.target) })
							}}
						</p>
						<p v-if="summary.production.position" class="mt-1.5 text-xs text-gray-600">{{ ctrans("#:position of :count suppliers in :country", { position: String(summary.production.position), count: String(summary.production.of), country: summary.country?.name ?? "" }) }}</p>
						<p v-else class="mt-1.5 text-xs text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
					</template>
					<p v-else class="text-sm text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
				</template>
				<template v-if="summary?.production" #note>
					{{
						summary.production.orders
							? ctrans("CHO = Clean Hand Over. Average days from :start to CHO of your :count orders handed over in the last 12 months; ranked from :minimum orders.", { start: summary.production.start, count: String(summary.production.orders), minimum: String(summary.production.minimum) })
							: ctrans("CHO = Clean Hand Over. Days from :start to CHO, once your agent or our team records both dates on your orders; ranked from :minimum orders.", { start: summary.production.start, minimum: String(summary.production.minimum) })
					}}
				</template>
			</SupplierPortalStatCard>

			<SupplierPortalStatCard :label="ctrans('Products listed')" icon="fal fa-box" :accent="accent" :loading="!sales" clickable @click="emit('go', 'products')">
				<template v-if="sales">
					<p class="text-2xl font-semibold tabular-nums text-gray-900">{{ sales.products }}</p>
					<p class="mt-1.5 text-xs text-gray-600">{{ ctrans(":count sold in the last 12 months", { count: String(sales.selling) }) }}</p>
					<div v-if="sales.products" class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
						<div class="h-full rounded-full" :style="{ width: `${Math.min(100, Math.round((sales.selling / sales.products) * 100))}%`, backgroundColor: accent.colour }" />
					</div>
				</template>
			</SupplierPortalStatCard>
		</section>

		<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
			<div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
				<section class="rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3">
						<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
							<FontAwesomeIcon icon="fal fa-clipboard" fixed-width aria-hidden="true" />
						</span>
						<div class="min-w-0 flex-1">
							<h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Your open purchase orders") }}</h2>
							<p class="text-xs text-gray-500">
								{{ ctrans(":count sent to you and not received yet", { count: String(openOrders) }) }}
								<template v-if="needsAttention">
									·
									<span class="font-medium text-red-600">{{ ctrans(":count need attention", { count: String(needsAttention) }) }}</span>
								</template>
							</p>
						</div>
						<Button type="tertiary" size="xs" :label="ctrans('See all')" @click="emit('go', 'purchase_orders')">
							<template #icon><FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" /></template>
						</Button>
					</header>

					<div v-if="!orders" class="flex flex-col gap-2 p-4">
						<div v-for="row in 3" :key="row" class="skeleton h-10 rounded" />
					</div>
					<p v-else-if="!openRibbons.length" class="flex items-center gap-2 px-4 py-6 text-sm text-gray-500">
						<FontAwesomeIcon icon="fal fa-check-circle" fixed-width class="text-green-600" aria-hidden="true" />
						{{ ctrans("No open orders right now.") }}
					</p>
					<ul v-else class="divide-y divide-gray-100">
						<li v-for="ribbon in shownRibbons" :key="ribbon.key">
							<button type="button" class="group flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50" @click="emit('open', ribbon.id)">
								<div class="min-w-0 flex-1">
									<p class="flex items-baseline gap-2">
										<span class="whitespace-nowrap font-mono text-sm font-semibold text-gray-900">{{ ribbon.reference }}</span>
										<span v-if="orderAmount(ribbon)" class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ orderAmount(ribbon) }}</span>
									</p>
									<p class="truncate text-xs text-gray-500">
										{{ ribbon.current_label }}
										<template v-if="ribbon.eta">· {{ ctrans("Due :date", { date: useFormatTime(ribbon.eta) }) }}</template>
									</p>
								</div>
								<span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium" :class="statusPill(ribbon).class">
									<FontAwesomeIcon :icon="statusPill(ribbon).icon" fixed-width aria-hidden="true" />
									{{ statusPill(ribbon).label }}
								</span>
								<FontAwesomeIcon icon="fal fa-chevron-right" class="text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" aria-hidden="true" />
							</button>
						</li>
					</ul>
					<button
						v-if="hiddenRibbons"
						type="button"
						class="w-full border-t border-gray-100 px-4 py-2.5 text-left text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-700"
						@click="emit('go', 'purchase_orders')">
						{{ ctrans(":count more open orders", { count: String(hiddenRibbons) }) }}
					</button>
				</section>
			</div>

			<aside class="flex min-w-0 flex-col gap-4 lg:gap-6">
				<section v-if="agent || buyers?.length" class="rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3">
						<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
							<FontAwesomeIcon icon="fal fa-users" fixed-width aria-hidden="true" />
						</span>
						<h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Your contacts at :group", { group }) }}</h2>
					</header>
					<ul class="divide-y divide-gray-100">
						<li v-if="agent" class="flex gap-3 p-4">
							<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :style="{ backgroundColor: accent.tint, color: accent.colour }">
								<FontAwesomeIcon icon="fal fa-user-tie" fixed-width aria-hidden="true" />
							</span>
							<div class="min-w-0 text-sm">
								<p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Your agent") }}</p>
								<p class="font-semibold text-gray-900">{{ agent.name }}</p>
								<p v-if="agent.contact_name" class="text-gray-500">{{ agent.contact_name }}</p>
								<div class="mt-1.5 flex flex-col gap-1 text-gray-700">
									<a v-if="agent.email" :href="`mailto:${agent.email}`" class="flex min-w-0 items-center gap-1.5 hover:underline"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" /><span class="truncate">{{ agent.email }}</span></a>
									<span v-if="agent.phone" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ agent.phone }}</span>
								</div>
							</div>
						</li>
						<li v-for="buyer in buyers ?? []" :key="buyer.organisation" class="flex items-center gap-3 px-4 py-3">
							<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">{{ initials(buyer.name ?? buyer.organisation) }}</span>
							<div class="min-w-0 text-sm">
								<p class="truncate font-semibold text-gray-900">{{ buyer.name }}</p>
								<p class="truncate text-xs text-gray-500">{{ ctrans("Purchasing, :organisation", { organisation: buyer.organisation }) }}</p>
							</div>
						</li>
					</ul>
				</section>

				<section v-if="company" class="rounded-lg border border-gray-200 bg-white shadow-sm">
					<header class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3">
						<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
							<FontAwesomeIcon icon="fal fa-building" fixed-width aria-hidden="true" />
						</span>
						<h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Your company") }}</h2>
					</header>
					<div class="p-4">
						<p class="font-semibold text-gray-900">{{ company.name }}</p>
						<p v-if="company.contact_name" class="text-sm text-gray-500">{{ company.contact_name }}</p>
						<ul class="mt-3 flex flex-col gap-2 text-sm text-gray-700">
							<li v-if="company.location" class="flex items-center gap-2"><FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width class="text-gray-400" aria-hidden="true" />{{ company.location }}</li>
							<li v-if="company.email" class="flex min-w-0 items-center gap-2"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" /><span class="truncate">{{ company.email }}</span></li>
							<li v-if="company.phone" class="flex items-center gap-2"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ company.phone }}</li>
						</ul>
						<p v-if="company.currency" class="mt-3 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ ctrans("Trading currency: :currency", { currency: company.currency }) }}</p>
					</div>
				</section>
			</aside>
		</div>
	</div>
</template>
