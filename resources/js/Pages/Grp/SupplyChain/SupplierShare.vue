<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SalesDashboard from "@/Components/Procurement/SalesDashboard.vue"
import SupplierPortalDetails from "@/Components/Procurement/SupplierPortalDetails.vue"
import TableSupplierPortalPurchaseOrders from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalPurchaseOrders.vue"
import SupplierPortalPriceChanges from "@/Components/Procurement/SupplierPortalPriceChanges.vue"
import SupplierPortalPurchaseOrderStages from "@/Components/Procurement/SupplierPortalPurchaseOrderStages.vue"
import SupplierPortalLanguages from "@/Components/Procurement/SupplierPortalLanguages.vue"
import SupplierPortalProfile, { type PortalProfile } from "@/Components/Procurement/SupplierPortalProfile.vue"
import SupplierPortalSearch from "@/Components/Procurement/SupplierPortalSearch.vue"
import SupplierPortalStatCard from "@/Components/Procurement/SupplierPortalStatCard.vue"
import Image from "@common/Components/Image.vue"
import { useColorTheme } from "@/Composables/useStockList"
import GrpAuth from "@/Layouts/GrpAuth.vue"
import type { PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import type { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import SupplierPortalPurchaseOrderDialog from "@/Components/Procurement/SupplierPortalPurchaseOrderDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart, faComputerClassic } from "@fas"
import { faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt, faHome, faChartLine, faClipboard, faList, faBox, faHandshake, faSeedling, faTags, faEnvelopeOpenText, faEye, faTrophy, faStopwatch, faBuilding, faUsers, faArrowUp, faArrowDown } from "@fal"

library.add(faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt, faHome, faChartLine, faClipboard, faList, faBox, faHandshake, faSeedling, faTags, faHeart, faComputerClassic, faEnvelopeOpenText, faEye, faTrophy, faStopwatch, faBuilding, faUsers, faArrowUp, faArrowDown)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

interface Person {
	contact_name: string | null
	email: string | null
	phone: string | null
}

interface Summary {
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

const props = defineProps<{
	title: string
	supplier: { code: string; name: string }
	company?: Person & { name: string; location: string | null; currency: string | null }
	agent?: (Person & { name: string }) | null
	summary?: Summary
	details?: InstanceType<typeof SupplierPortalDetails>["$props"]["details"]
	forms?: PortalForms
	submitted?: string | null
	group: string
	language: string
	routes: { send_code: string; verify: string; profile: string; search: string }
	profile?: PortalProfile
	needs_code: boolean
	preview?: boolean
	email?: string | null
	code_sent?: boolean
	valuation?: Valuation
	purchase_orders?: InstanceType<typeof TableSupplierPortalPurchaseOrders>["$props"]["data"]
	purchase_order_stages?: InstanceType<typeof SupplierPortalPurchaseOrderStages>["$props"]["data"]
	price_changes?: InstanceType<typeof SupplierPortalPriceChanges>["$props"]["data"]
	open_purchase_orders?: number
	errors?: Record<string, string>
	sales?: InstanceType<typeof SalesDashboard>["$props"]["sales"]
	feedback?: InstanceType<typeof SalesDashboard>["$props"]["feedback"]
	labels_print?: InstanceType<typeof SupplierPortalDetails>["$props"]["labelsPrint"]
	products_route?: string
	purchase_order_route?: string
}>()

const code = ref("")
const isSending = ref(false)
const isChecking = ref(false)

const sendCode = () => {
	isSending.value = true
	router.post(props.routes.send_code, {}, { preserveScroll: true, onFinish: () => (isSending.value = false) })
}

const verify = () => {
	isChecking.value = true
	router.post(props.routes.verify, { code: code.value }, { onFinish: () => (isChecking.value = false) })
}

const money = (amount: number, currency: string | null) =>
	currency ? new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 0 }).format(Math.round(amount)) : Math.round(amount).toString()

const welcome = computed(() => [
	ctrans("We believe good business starts with trust, honesty and straightforward conversations. We take responsibility for our part, respect the people who make our products, and bring generosity, good humour and care to the work we share. We welcome your ideas and want to build a lasting, successful partnership."),
	ctrans("Thank you for your care and attention to products supplied to :group. Together we can sell more of your products.", { group: props.group }),
])

const openOrders = computed(() => props.open_purchase_orders ?? 0)
const changeLabel = (change: number) =>
	change >= 0 ? ctrans(":percent% more than the 12 months before", { percent: String(change) }) : ctrans(":percent% less than the 12 months before", { percent: String(Math.abs(change)) })

const theme = ref(props.profile?.theme ?? 0)
const navigationTheme = computed(() => {
	const colours = useColorTheme[theme.value] ?? useColorTheme[0]

	return {
		background: colours[0],
		text: colours[1],
		soft: `color-mix(in srgb, ${colours[0]} 6%, white)`,
		badge: `color-mix(in srgb, ${colours[1]} 18%, transparent)`,
		active: { backgroundColor: colours[2], color: colours[3], boxShadow: `0 0 0 1px color-mix(in srgb, ${colours[2]}, 20% white)` },
	}
})

const accent = computed(() => ({
	colour: navigationTheme.value.background,
	tint: `color-mix(in srgb, ${navigationTheme.value.background} 12%, white)`,
}))

const initials = (name: string) =>
	name
		.split(/\s+/)
		.filter(Boolean)
		.slice(0, 2)
		.map((word) => word.charAt(0))
		.join("")
		.toUpperCase()

type Tab = "home" | "sales" | "purchase_orders" | "purchase_order_list" | "products" | "price_changes" | "agreement" | "grow"

const fullWidthTabs: Tab[] = ["purchase_orders", "purchase_order_list"]

const menu = computed<{ key: Tab; label: string; icon: string; shortcut?: boolean }[]>(() => [
	{ key: "home", label: ctrans("Home"), icon: "fal fa-home" },
	{ key: "sales", label: ctrans("Sales"), icon: "fal fa-chart-line" },
	{ key: "purchase_orders", label: ctrans("Purchase orders"), icon: "fal fa-clipboard" },
	{ key: "purchase_order_list", label: ctrans("Purchase order list"), icon: "fal fa-list", shortcut: true },
	{ key: "products", label: ctrans("Products"), icon: "fal fa-box" },
	{ key: "price_changes", label: ctrans("Price changes"), icon: "fal fa-tags" },
	{ key: "agreement", label: ctrans("Agreement"), icon: "fal fa-handshake" },
	{ key: "grow", label: ctrans("Grow together"), icon: "fal fa-seedling" },
])

const tab = ref<Tab>("home")
const currentMenu = computed(() => menu.value.find((item) => item.key === tab.value))

const loadedDetails = ref(props.details)
watch(() => props.details, (details) => details && (loadedDetails.value = details))

const formType = ref<string | undefined>(undefined)

const openCompanyDetails = () => {
	formType.value = "company_details"
	go("grow")
}

const productSearch = ref<string | undefined>(undefined)

const openProduct = (code: string) => {
	productSearch.value = code
	go("products")
}

const openPurchaseOrderId = ref<number | null>(null)

const openOrder = (reference: string) => {
	const url = new URL(window.location.href)
	url.searchParams.set("tab", "purchase_order_list")
	url.searchParams.set("purchase_orders_filter[global]", reference)
	url.searchParams.delete("purchase_ordersPage")
	tab.value = "purchase_order_list"
	router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, only: ["purchase_orders"] })
}

const go = (key: Tab) => {
	tab.value = key
	const url = new URL(window.location.href)
	url.searchParams.set("tab", key)
	window.history.replaceState(window.history.state, "", url)
	window.scrollTo({ top: 0 })
}

onMounted(() => {
	const requested = new URLSearchParams(window.location.search).get("tab")
	if (menu.value.some((item) => item.key === requested)) {
		tab.value = requested as Tab
	}
})
</script>

<template>
	<Head :title="title" />
	<GrpAuth v-if="needs_code">
		<div class="space-y-1 pb-4">
			<h1 class="text-base font-semibold text-gray-800">{{ supplier.name }}</h1>
		</div>
		<section class="space-y-3">
			<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700">
				<FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
				{{ ctrans("Confirm it is you") }}
			</h2>
			<p class="text-sm text-gray-600">{{ ctrans("We send a code to :email. Enter it here to see the page.", { email: email ?? "" }) }}</p>
			<Button type="tertiary" size="xs" :label="code_sent ? ctrans('Send a new code') : ctrans('Send code')" :loading="isSending" @click="sendCode">
				<template #icon><FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" /></template>
			</Button>
			<p v-if="code_sent" class="text-xs text-green-700">{{ ctrans("Code sent. It works for 10 minutes.") }}</p>
			<form class="flex items-center gap-2" @submit.prevent="verify">
				<InputText v-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="w-36 text-center font-mono tracking-widest" :placeholder="ctrans('Code')" />
				<Button type="tertiary" size="xs" :label="ctrans('Open')" :loading="isChecking" :disabled="code.length < 6" @click="verify" />
			</form>
			<p v-if="errors?.code" class="text-xs text-red-600">{{ errors.code }}</p>
		</section>
		<template #below>
			<SupplierPortalLanguages :language="language" class="mt-4" />
		</template>
	</GrpAuth>

	<div v-else class="min-h-[100dvh] bg-gray-50 lg:flex">
		<aside class="lg:sticky lg:top-0 lg:flex lg:h-[calc(100dvh-1.5rem)] lg:w-64 lg:shrink-0 lg:flex-col" :style="{ backgroundColor: navigationTheme.background, color: navigationTheme.text }">
			<div class="flex items-center gap-3 px-4 pb-3 pt-4 lg:px-5 lg:pb-5 lg:pt-6">
				<div class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg text-sm font-bold" :style="{ backgroundColor: navigationTheme.badge }">
					<Image v-if="profile?.company_logo" :src="profile.company_logo" class="absolute inset-0 h-full w-full object-cover" :alt="supplier.name" />
					<template v-else>{{ initials(supplier.name) }}</template>
				</div>
				<div class="min-w-0">
					<div class="truncate text-sm font-semibold leading-tight">{{ supplier.name }}</div>
					<div class="truncate text-xs opacity-70">{{ ctrans("Supplier page with :group", { group }) }}</div>
				</div>
			</div>
			<div class="mx-5 hidden border-t lg:block" :style="{ borderColor: navigationTheme.badge }" />
			<nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-1 lg:flex-col lg:overflow-y-auto lg:pb-4 lg:pt-4" :aria-label="ctrans('Menu')">
				<button
					v-for="item in menu"
					:key="item.key"
					type="button"
					class="flex shrink-0 items-center gap-2.5 whitespace-nowrap rounded-md px-3 text-sm transition"
					:class="[tab === item.key ? 'font-semibold' : 'font-medium opacity-80 hover:bg-white/10 hover:opacity-100', item.shortcut ? 'py-1.5 text-xs lg:ml-6' : 'py-2']"
					:style="tab === item.key ? navigationTheme.active : undefined"
					:aria-current="tab === item.key ? 'page' : undefined"
					@click="go(item.key)">
					<FontAwesomeIcon :icon="item.icon" fixed-width aria-hidden="true" />
					{{ item.label }}
				</button>
			</nav>
		</aside>

		<main class="min-w-0 flex-1 pb-12 lg:pb-14">
			<div v-if="preview" class="flex items-center justify-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-xs font-medium text-amber-900 lg:px-8">
				<FontAwesomeIcon icon="fal fa-eye" fixed-width aria-hidden="true" />
				{{ ctrans("Preview: this is what :supplier sees. Nothing you do here is sent or saved, and it does not count as a visit.", { supplier: supplier.name }) }}
			</div>
			<header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur lg:px-8">
				<span v-if="currentMenu" class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg sm:flex" :style="{ backgroundColor: accent.tint, color: accent.colour }">
					<FontAwesomeIcon :icon="currentMenu.icon" fixed-width aria-hidden="true" />
				</span>
				<h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-gray-900">{{ currentMenu?.label }}</h1>
				<SupplierPortalSearch :search-route="routes.search" @product="openProduct" @order="openOrder" />
				<SupplierPortalProfile v-if="profile" :profile="profile" :update-route="routes.profile" v-model:theme="theme" @company-details="openCompanyDetails" />
			</header>
			<div class="mx-auto flex flex-col gap-4 p-4 lg:gap-6 lg:p-8" :class="fullWidthTabs.includes(tab) ? 'max-w-none' : 'max-w-6xl'">

				<template v-if="tab === 'home'">
					<article class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
						<header class="flex items-center gap-3 px-5 py-3 sm:px-6" :style="{ backgroundColor: navigationTheme.background, color: navigationTheme.text }">
							<div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold" :style="{ backgroundColor: navigationTheme.badge }">
								{{ initials(group) }}
							</div>
							<div class="min-w-0 flex-1">
								<p class="truncate text-sm font-semibold">{{ ctrans("A message from :group", { group }) }}</p>
								<p class="truncate text-xs opacity-75">{{ ctrans("We are pleased to be your distribution partner in Europe.") }}</p>
							</div>
							<FontAwesomeIcon icon="fal fa-envelope-open-text" class="hidden text-xl opacity-60 sm:block" aria-hidden="true" />
						</header>
						<div class="px-5 py-5 sm:px-6 sm:py-6" :style="{ backgroundColor: navigationTheme.soft }">
							<div class="flex max-w-3xl flex-col gap-3 text-sm leading-relaxed text-gray-700">
								<p class="font-semibold text-gray-900">{{ ctrans("Dear :supplier,", { supplier: supplier.name }) }}</p>
								<p>{{ ctrans("Thank you for trading with :group.", { group }) }}</p>
								<p>{{ welcome[0] }}</p>
								<p class="border-l-2 pl-3 font-medium text-gray-900" :style="{ borderColor: navigationTheme.background }">{{ welcome[1] }}</p>
							</div>
							<footer class="mt-6 text-sm">
								<p class="text-gray-500">{{ ctrans("With warm regards,") }}</p>
								<p class="mt-1 font-serif text-base italic text-gray-900">{{ ctrans("The :group team", { group }) }}</p>
							</footer>
						</div>
					</article>

					<section class="grid gap-4 md:grid-cols-3">
						<SupplierPortalStatCard :label="ctrans('Sales in the last 12 months')" icon="fal fa-chart-line" :accent="accent" :loading="!summary">
							<template v-if="summary">
								<p class="text-2xl font-semibold tabular-nums text-gray-900">{{ money(summary.sales, summary.currency) }}</p>
								<p
									v-if="summary.change !== null"
									class="mt-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
									:class="summary.change >= 0 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
									<FontAwesomeIcon :icon="summary.change >= 0 ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width aria-hidden="true" />
									{{ changeLabel(summary.change) }}
								</p>
								<p v-else class="mt-1.5 text-xs text-gray-500">{{ ctrans("No sales in the 12 months before to compare with") }}</p>
							</template>
							<template v-if="summary" #note>
								{{ ctrans("At your prices, UK, Spain and Slovakia together, :from to :to. Updated :date.", { from: useFormatTime(summary.from), to: useFormatTime(summary.to), date: useFormatTime(summary.as_of) }) }}
							</template>
						</SupplierPortalStatCard>

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
												? ctrans("+:days days above the :target day target", { days: String(Math.round((summary.production.average - summary.production.target) * 10) / 10), target: String(summary.production.target) })
												: ctrans(":days days below the :target day target", { days: String(Math.round((summary.production.target - summary.production.average) * 10) / 10), target: String(summary.production.target) })
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
					</section>

					<section class="grid gap-4 sm:grid-cols-2">
						<SupplierPortalStatCard :label="ctrans('Products listed')" icon="fal fa-box" :accent="accent" :loading="!sales" clickable @click="go('products')">
							<div v-if="sales" class="flex items-baseline gap-2">
								<p class="text-2xl font-semibold tabular-nums text-gray-900">{{ sales.products }}</p>
								<p class="text-xs text-gray-600">{{ ctrans(":count sold in the last 12 months", { count: String(sales.selling) }) }}</p>
							</div>
						</SupplierPortalStatCard>

						<SupplierPortalStatCard :label="ctrans('Open purchase orders')" icon="fal fa-clipboard" :accent="accent" clickable @click="go('purchase_orders')">
							<div class="flex items-baseline gap-2">
								<p class="text-2xl font-semibold tabular-nums text-gray-900">{{ openOrders }}</p>
								<p class="text-xs text-gray-600">{{ ctrans("Sent to you and not received yet") }}</p>
							</div>
						</SupplierPortalStatCard>
					</section>

					<section class="grid gap-4 md:grid-cols-2">
						<div v-if="company" class="rounded-lg border border-gray-200 bg-white shadow-sm">
							<div class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3">
								<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
									<FontAwesomeIcon icon="fal fa-building" fixed-width aria-hidden="true" />
								</span>
								<h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Your company") }}</h2>
							</div>
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
						</div>

						<div v-if="agent || details?.buyers.length" class="rounded-lg border border-gray-200 bg-white shadow-sm">
							<div class="flex items-center gap-2.5 border-b border-gray-100 px-4 py-3">
								<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
									<FontAwesomeIcon icon="fal fa-users" fixed-width aria-hidden="true" />
								</span>
								<h2 class="text-sm font-semibold text-gray-800">{{ ctrans("Your contacts at :group", { group }) }}</h2>
							</div>
							<ul class="divide-y divide-gray-100">
								<li v-if="agent" class="flex gap-3 p-4">
									<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold" :style="{ backgroundColor: accent.tint, color: accent.colour }">
										<FontAwesomeIcon icon="fal fa-user-tie" fixed-width aria-hidden="true" />
									</span>
									<div class="min-w-0 text-sm">
										<p class="font-semibold text-gray-900">{{ ctrans("Your agent: :agent", { agent: agent.name }) }}</p>
										<p v-if="agent.contact_name" class="text-gray-500">{{ agent.contact_name }}</p>
										<div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-gray-700">
											<a v-if="agent.email" :href="`mailto:${agent.email}`" class="flex min-w-0 items-center gap-1.5 hover:underline"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" /><span class="truncate">{{ agent.email }}</span></a>
											<span v-if="agent.phone" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ agent.phone }}</span>
										</div>
									</div>
								</li>
								<li v-for="buyer in details?.buyers ?? []" :key="buyer.organisation" class="flex items-center gap-3 px-4 py-3">
									<span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">{{ initials(buyer.name ?? buyer.organisation) }}</span>
									<div class="min-w-0 text-sm">
										<p class="truncate font-semibold text-gray-900">{{ buyer.name }}</p>
										<p class="truncate text-xs text-gray-500">{{ ctrans("Purchasing, :organisation", { organisation: buyer.organisation }) }}</p>
									</div>
								</li>
							</ul>
						</div>
					</section>
				</template>

				<SalesDashboard v-else-if="tab === 'sales' && valuation" seller="supplier" :staff="false" :links="null" :valuation="valuation" :sales="sales" :feedback="feedback" :language="language" />

				<template v-else-if="tab === 'purchase_orders'">
					<SupplierPortalPurchaseOrderStages v-if="purchase_order_stages" :data="purchase_order_stages" @list="go('purchase_order_list')" @open="openPurchaseOrderId = $event" />
					<div v-else class="skeleton h-40 rounded-lg" />
				</template>

				<div v-else-if="tab === 'purchase_order_list'" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<p class="px-4 pt-4 text-sm text-gray-500">{{ ctrans("Every order we sent you. Open orders show the labels to print.") }}</p>
					<TableSupplierPortalPurchaseOrders v-if="purchase_orders" :data="purchase_orders" tab="purchase_orders" @open="openPurchaseOrderId = $event" />
				</div>

				<SupplierPortalPriceChanges
					v-else-if="tab === 'price_changes' && price_changes && forms"
					:data="price_changes"
					tab="price_changes"
					:forms="forms"
					:currency="details?.currency ?? company?.currency ?? null" />

				<template v-else>
					<SupplierPortalDetails v-if="loadedDetails && forms" :details="loadedDetails" :forms="forms" :submitted="submitted ?? null" :section="tab as 'products' | 'agreement' | 'grow'" :initial-form-type="formType" :initial-search="productSearch" :labels-print="labels_print" :products-route="products_route" />
					<div v-else class="skeleton h-40 rounded-lg" />
				</template>

			</div>
		</main>

		<SupplierPortalPurchaseOrderDialog v-if="purchase_order_route" v-model="openPurchaseOrderId" :purchase-order-route="purchase_order_route" />

		<footer class="fixed bottom-0 left-0 z-20 flex h-6 w-screen items-center justify-between gap-x-4 bg-black px-4 text-xs text-slate-400">
			<a href="https://aiku.io/" target="_blank" rel="noopener" class="flex shrink-0 items-center gap-x-1.5 whitespace-nowrap hover:text-white">
				<img class="inline h-3 select-none" src="/art/invader.svg" alt="aiku" />
				<span class="hidden sm:inline">
					{{ ctrans("Made with") }}
					<FontAwesomeIcon icon="fas fa-heart" class="mx-1 text-pink-500" fixed-width aria-hidden="true" />
					{{ ctrans("and") }}
					<FontAwesomeIcon icon="fas fa-computer-classic" class="mx-1" fixed-width aria-hidden="true" /> {{ "in KL|Bali" }}
				</span>
			</a>
			<SupplierPortalLanguages :language="language" dark class="min-w-0 overflow-x-auto" />
		</footer>
	</div>
</template>
