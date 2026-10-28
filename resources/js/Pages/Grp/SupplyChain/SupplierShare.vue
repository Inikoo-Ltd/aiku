<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SalesDashboard from "@/Components/Procurement/SalesDashboard.vue"
import SupplierPortalDetails from "@/Components/Procurement/SupplierPortalDetails.vue"
import TableSupplierPortalPurchaseOrders from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalPurchaseOrders.vue"
import SupplierPortalPriceChanges from "@/Components/Procurement/SupplierPortalPriceChanges.vue"
import SupplierPortalLanguages from "@/Components/Procurement/SupplierPortalLanguages.vue"
import SupplierPortalProfile, { type PortalProfile } from "@/Components/Procurement/SupplierPortalProfile.vue"
import SupplierPortalSearch from "@/Components/Procurement/SupplierPortalSearch.vue"
import { useColorTheme } from "@/Composables/useStockList"
import GrpAuth from "@/Layouts/GrpAuth.vue"
import type { PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import type { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart, faComputerClassic } from "@fas"
import { faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt, faHome, faChartLine, faClipboard, faBox, faHandshake, faSeedling, faTags } from "@fal"

library.add(faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt, faHome, faChartLine, faClipboard, faBox, faHandshake, faSeedling, faTags, faHeart, faComputerClassic)

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
	price_changes?: InstanceType<typeof SupplierPortalPriceChanges>["$props"]["data"]
	open_purchase_orders?: number
	errors?: Record<string, string>
	sales?: InstanceType<typeof SalesDashboard>["$props"]["sales"]
	feedback?: InstanceType<typeof SalesDashboard>["$props"]["feedback"]
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
		active: { backgroundColor: colours[2], color: colours[3], boxShadow: `0 0 0 1px color-mix(in srgb, ${colours[2]}, 20% white)` },
	}
})

type Tab = "home" | "sales" | "purchase_orders" | "products" | "price_changes" | "agreement" | "grow"

const menu = computed<{ key: Tab; label: string; icon: string }[]>(() => [
	{ key: "home", label: ctrans("Home"), icon: "fal fa-home" },
	{ key: "sales", label: ctrans("Sales"), icon: "fal fa-chart-line" },
	{ key: "purchase_orders", label: ctrans("Purchase orders"), icon: "fal fa-clipboard" },
	{ key: "products", label: ctrans("Products"), icon: "fal fa-box" },
	{ key: "price_changes", label: ctrans("Price changes"), icon: "fal fa-tags" },
	{ key: "agreement", label: ctrans("Agreement"), icon: "fal fa-handshake" },
	{ key: "grow", label: ctrans("Grow together"), icon: "fal fa-seedling" },
])

const tab = ref<Tab>("home")

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

const openOrder = (reference: string) => {
	const url = new URL(window.location.href)
	url.searchParams.set("tab", "purchase_orders")
	url.searchParams.set("purchase_orders_filter[global]", reference)
	url.searchParams.delete("purchase_ordersPage")
	tab.value = "purchase_orders"
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
		<aside class="lg:sticky lg:top-0 lg:flex lg:h-[calc(100dvh-1.5rem)] lg:w-64 lg:shrink-0 lg:flex-col" :style="{ backgroundColor: navigationTheme.background }">
			<div class="px-4 pb-2 pt-4 lg:px-5 lg:pt-6">
				<div class="text-base font-semibold leading-tight" :style="{ color: navigationTheme.text }">{{ supplier.name }}</div>
				<div class="text-xs opacity-70" :style="{ color: navigationTheme.text }">{{ ctrans("Supplier page with :group", { group }) }}</div>
			</div>
			<nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible lg:px-3 lg:pb-0 lg:pt-2" :aria-label="ctrans('Menu')">
				<button
					v-for="item in menu"
					:key="item.key"
					type="button"
					class="flex shrink-0 items-center gap-2.5 whitespace-nowrap rounded px-3 py-2 text-sm font-semibold transition"
					:class="tab === item.key ? '' : 'hover:bg-gray-300/40'"
					:style="tab === item.key ? navigationTheme.active : { color: navigationTheme.text }"
					:aria-current="tab === item.key ? 'page' : undefined"
					@click="go(item.key)">
					<FontAwesomeIcon :icon="item.icon" fixed-width aria-hidden="true" />
					{{ item.label }}
				</button>
			</nav>
		</aside>

		<main class="min-w-0 flex-1 pb-12 lg:pb-14">
			<div v-if="preview" class="bg-amber-100 px-4 py-1.5 text-center text-xs font-medium text-amber-900 lg:px-8">
				{{ ctrans("Preview: this is what :supplier sees. Nothing you do here is sent or saved, and it does not count as a visit.", { supplier: supplier.name }) }}
			</div>
			<header class="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-gray-200 bg-white/95 px-4 backdrop-blur lg:px-8">
				<h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-gray-900">{{ menu.find((item) => item.key === tab)?.label }}</h1>
				<SupplierPortalSearch :search-route="routes.search" @product="openProduct" @order="openOrder" />
				<SupplierPortalProfile v-if="profile" :profile="profile" :update-route="routes.profile" v-model:theme="theme" @company-details="openCompanyDetails" />
			</header>
			<div class="mx-auto max-w-6xl space-y-4 p-4 lg:p-8">

				<template v-if="tab === 'home'">
					<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
						<p class="text-sm font-semibold text-gray-800">
							{{ ctrans("Thank you for trading with :group.", { group }) }}
							<span class="font-normal text-gray-700">{{ ctrans("We are pleased to be your distribution partner in Europe.") }}</span>
						</p>
						<details class="mt-1 text-sm text-gray-600">
							<summary class="cursor-pointer select-none text-xs text-gray-400">{{ ctrans("Read our message") }}</summary>
							<p class="mt-2">{{ welcome[0] }}</p>
							<p class="mt-2 font-semibold text-gray-700">{{ welcome[1] }}</p>
						</details>
					</div>
	<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
						<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Sales in the last 12 months") }}</h2>
							<template v-if="summary">
								<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ money(summary.sales, summary.currency) }}</p>
								<p v-if="summary.change !== null" class="text-xs" :class="summary.change >= 0 ? 'text-green-700' : 'text-red-600'">{{ changeLabel(summary.change) }}</p>
								<p v-else class="text-xs text-gray-400">{{ ctrans("No sales in the 12 months before to compare with") }}</p>
								<p class="mt-2 text-xs text-gray-500">{{ ctrans("At your prices, UK, Spain and Slovakia together, :from to :to. Updated :date.", { from: useFormatTime(summary.from), to: useFormatTime(summary.to), date: useFormatTime(summary.as_of) }) }}</p>
							</template>
							<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
						</div>
	
						<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
								{{ summary?.country ? ctrans("Ranking by sales in :country", { country: summary.country.name }) : ctrans("Ranking by sales") }}
							</h2>
							<template v-if="summary">
								<template v-if="summary.ranking">
									<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">#{{ summary.ranking.position }}</p>
									<p class="text-xs text-gray-600">{{ ctrans(":position of :count suppliers in :country", { position: String(summary.ranking.position), count: String(summary.ranking.of), country: summary.country?.name ?? "" }) }}</p>
								</template>
								<p v-else class="mt-1 text-sm text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
								<p class="mt-2 text-xs text-gray-500">{{ ctrans("Against every :group supplier based in your country with sales in the same 12 months, with an agent or without, each at its own prices. Other suppliers are never named.", { group }) }}</p>
							</template>
							<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
						</div>
	
						<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">
								{{ summary?.country ? ctrans("Production time to CHO in :country", { country: summary.country.name }) : ctrans("Production time to CHO") }}
							</h2>
							<template v-if="summary?.production">
								<template v-if="summary.production.average !== null">
									<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ ctrans(":days days", { days: String(summary.production.average) }) }}</p>
									<p class="text-xs" :class="summary.production.average > summary.production.target ? 'text-amber-700' : 'text-green-700'">
										{{
											summary.production.average > summary.production.target
												? ctrans("+:days days above the :target day target", { days: String(Math.round((summary.production.average - summary.production.target) * 10) / 10), target: String(summary.production.target) })
												: ctrans(":days days below the :target day target", { days: String(Math.round((summary.production.target - summary.production.average) * 10) / 10), target: String(summary.production.target) })
										}}
									</p>
									<p v-if="summary.production.position" class="text-xs text-gray-600">{{ ctrans("#:position of :count suppliers in :country", { position: String(summary.production.position), count: String(summary.production.of), country: summary.country?.name ?? "" }) }}</p>
									<p v-else class="text-xs text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
								</template>
								<p v-else class="mt-1 text-sm text-gray-500">{{ ctrans("Not enough data to rank") }}</p>
								<p class="mt-2 text-xs text-gray-500">
									{{
										summary.production.orders
											? ctrans("CHO = Clean Hand Over. Average days from :start to CHO of your :count orders handed over in the last 12 months; ranked from :minimum orders.", { start: summary.production.start, count: String(summary.production.orders), minimum: String(summary.production.minimum) })
											: ctrans("CHO = Clean Hand Over. Days from :start to CHO, once your agent or our team records both dates on your orders; ranked from :minimum orders.", { start: summary.production.start, minimum: String(summary.production.minimum) })
									}}
								</p>
							</template>
							<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
						</div>
	
						<button type="button" class="block rounded-lg text-left border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300" @click="go('products')">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Products listed") }}</h2>
							<template v-if="sales">
								<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ sales.products }}</p>
								<p class="text-xs text-gray-600">{{ ctrans(":count sold in the last 12 months", { count: String(sales.selling) }) }}</p>
							</template>
							<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
						</button>
	
						<button type="button" class="block rounded-lg text-left border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300" @click="go('purchase_orders')">
							<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Open purchase orders") }}</h2>
							<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ openOrders }}</p>
							<p class="text-xs text-gray-600">{{ ctrans("Sent to you and not received yet") }}</p>
						</button>
					</section>
	<section class="grid gap-4 md:grid-cols-2">
						<div v-if="company" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
							<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Your company") }}</h2>
							<dl class="mt-2 space-y-1 text-sm text-gray-700">
								<div class="font-medium text-gray-800">{{ company.name }}</div>
								<div v-if="company.contact_name">{{ company.contact_name }}</div>
								<div v-if="company.location" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width class="text-gray-400" aria-hidden="true" />{{ company.location }}</div>
								<div v-if="company.email" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" />{{ company.email }}</div>
								<div v-if="company.phone" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ company.phone }}</div>
								<div v-if="company.currency" class="text-xs text-gray-500">{{ ctrans("Trading currency: :currency", { currency: company.currency }) }}</div>
							</dl>
						</div>
						<div v-if="agent || details?.buyers.length" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
							<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Your contacts at :group", { group }) }}</h2>
							<dl v-if="agent" class="mt-2 space-y-1 text-sm text-gray-700">
								<div class="flex items-center gap-1.5 font-medium text-gray-800">
									<FontAwesomeIcon icon="fal fa-user-tie" fixed-width class="text-gray-400" aria-hidden="true" />
									{{ ctrans("Your agent: :agent", { agent: agent.name }) }}<template v-if="agent.contact_name"> — {{ agent.contact_name }}</template>
								</div>
								<div v-if="agent.email" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" /><a :href="`mailto:${agent.email}`" class="hover:underline">{{ agent.email }}</a></div>
								<div v-if="agent.phone" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ agent.phone }}</div>
							</dl>
							<dl v-if="details?.buyers.length" class="mt-3 space-y-1 text-sm text-gray-700">
								<div v-for="buyer in details.buyers" :key="buyer.organisation">
									<span class="font-medium text-gray-800">{{ buyer.name }}</span>
									<span class="text-xs text-gray-500"> · {{ ctrans("Purchasing, :organisation", { organisation: buyer.organisation }) }}</span>
								</div>
							</dl>
						</div>
					</section>
				</template>

				<SalesDashboard v-else-if="tab === 'sales' && valuation" seller="supplier" :staff="false" :links="null" :valuation="valuation" :sales="sales" :feedback="feedback" :language="language" />

	<div v-else-if="tab === 'purchase_orders'" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
					<p class="px-4 pt-4 text-sm text-gray-500">{{ ctrans("Every order we sent you. Open orders show the labels to print.") }}</p>
					<TableSupplierPortalPurchaseOrders v-if="purchase_orders" :data="purchase_orders" tab="purchase_orders" />
				</div>

				<SupplierPortalPriceChanges
					v-else-if="tab === 'price_changes' && price_changes && forms"
					:data="price_changes"
					tab="price_changes"
					:forms="forms"
					:currency="details?.currency ?? company?.currency ?? null" />

				<template v-else>
					<SupplierPortalDetails v-if="details && forms" :details="details" :forms="forms" :submitted="submitted ?? null" :section="tab as 'products' | 'agreement' | 'grow'" :initial-form-type="formType" :initial-search="productSearch" />
					<div v-else class="h-40 animate-pulse rounded-lg bg-gray-100" />
				</template>

			</div>
		</main>

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
