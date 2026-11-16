<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SalesDashboard from "@/Components/Procurement/SalesDashboard.vue"
import SupplierPortalDetails from "@/Components/Procurement/SupplierPortalDetails.vue"
import SupplierPortalAgreement, { type PortalAgreement } from "@/Components/Procurement/SupplierPortalAgreement.vue"
import TableSupplierPortalPurchaseOrders from "@/Components/Tables/Grp/SupplyChain/TableSupplierPortalPurchaseOrders.vue"
import SupplierPortalPriceChanges from "@/Components/Procurement/SupplierPortalPriceChanges.vue"
import SupplierPortalPurchaseOrderStages from "@/Components/Procurement/SupplierPortalPurchaseOrderStages.vue"
import SupplierPortalLanguages from "@/Components/Procurement/SupplierPortalLanguages.vue"
import SupplierPortalProfile, { type PortalProfile } from "@/Components/Procurement/SupplierPortalProfile.vue"
import SupplierPortalSearch from "@/Components/Procurement/SupplierPortalSearch.vue"
import SupplierPortalHome, { type PortalSummary } from "@/Components/Procurement/SupplierPortalHome.vue"
import StockDeliveryFeedbackCards, { type FeedbackCard } from "@/Components/Procurement/StockDeliveryFeedbackCards.vue"
import Image from "@common/Components/Image.vue"
import type { Image as ImageProxy } from "@/types/Image"
import { useColorTheme } from "@/Composables/useStockList"
import { setColorStyleRoot } from "@/Composables/useApp"
import { useAppAccentVariables } from "@/Composables/useAppAccent"
import GrpAuth from "@/Layouts/GrpAuth.vue"
import type { PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import type { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import { ctrans } from "@/Composables/useTrans"
import SupplierPortalPurchaseOrderDialog from "@/Components/Procurement/SupplierPortalPurchaseOrderDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart, faComputerClassic } from "@fas"
import { faLock, faEnvelope, faHome, faChartLine, faClipboard, faList, faBox, faHandshake, faSeedling, faTags, faEye, faFileSignature, faStar } from "@fal"

library.add(faLock, faEnvelope, faHome, faChartLine, faClipboard, faList, faBox, faHandshake, faSeedling, faTags, faHeart, faComputerClassic, faEye, faFileSignature, faStar)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

interface Person {
	contact_name: string | null
	email: string | null
	phone: string | null
}

const props = defineProps<{
	title: string
	supplier: { code: string; name: string }
	company?: Person & { name: string; location: string | null; currency: string | null }
	agent?: (Person & { name: string }) | null
	owner?: { name: string | null; email: string | null } | null
	summary?: PortalSummary
	details?: InstanceType<typeof SupplierPortalDetails>["$props"]["details"]
	forms?: PortalForms
	submitted?: string | null
	group: string
	group_logo?: ImageProxy | null
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
	delivery_feedback?: FeedbackCard[]
	labels_print?: InstanceType<typeof SupplierPortalDetails>["$props"]["labelsPrint"]
	products_route?: string
	product_websites_route?: string
	purchase_order_route?: string
	agreement?: PortalAgreement | null
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

const openOrders = computed(() => props.open_purchase_orders ?? 0)

const theme = ref(props.profile?.theme ?? 0)
const themeColours = computed(() => useColorTheme[theme.value] ?? useColorTheme[0])
watch(themeColours, (colours) => setColorStyleRoot(colours), { immediate: true })
useAppAccentVariables(() => themeColours.value)

const navigationTheme = computed(() => {
	const colours = themeColours.value

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

type Tab = "home" | "sales" | "purchase_orders" | "purchase_order_list" | "products" | "price_changes" | "feedback" | "agreement" | "grow"

const fullWidthTabs: Tab[] = ["purchase_orders", "purchase_order_list"]

const menu = computed<{ key: Tab; label: string; icon: string; shortcut?: boolean }[]>(() => [
	{ key: "home", label: ctrans("Home"), icon: "fal fa-home" },
	{ key: "sales", label: ctrans("Sales"), icon: "fal fa-chart-line" },
	{ key: "purchase_orders", label: ctrans("Purchase orders"), icon: "fal fa-clipboard" },
	{ key: "purchase_order_list", label: ctrans("Purchase order list"), icon: "fal fa-list", shortcut: true },
	{ key: "products", label: ctrans("Products"), icon: "fal fa-box" },
	{ key: "price_changes", label: ctrans("Price changes"), icon: "fal fa-tags" },
	{ key: "feedback", label: ctrans("Delivery feedback"), icon: "fal fa-star" },
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

				<button
					v-if="tab === 'home' && agreement?.must_accept"
					type="button"
					class="flex w-full items-center gap-3 rounded-lg border px-4 py-3 text-left text-sm"
					:class="agreement.status.status === 'outdated' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-red-200 bg-red-50 text-red-900'"
					@click="go('agreement')">
					<FontAwesomeIcon icon="fal fa-file-signature" fixed-width aria-hidden="true" />
					<span class="flex-1">{{ agreement.status.status === 'outdated' ? ctrans("There is a new version of the Supplier Agreement to accept.") : ctrans("Please read and accept the Supplier Agreement.") }}</span>
					<span class="whitespace-nowrap font-medium underline">{{ ctrans("Open") }}</span>
				</button>

				<SupplierPortalHome
					v-if="tab === 'home'"
					:supplier="supplier"
					:group="group"
					:logo="profile?.company_logo"
					:group-logo="group_logo"
					:company="company"
					:agent="agent"
					:owner="owner"
					:buyers="loadedDetails?.buyers"
					:contacts-loading="!loadedDetails"
					:summary="summary"
					:sales="sales"
					:open-orders="openOrders"
					:orders="purchase_order_stages"
					:theme="navigationTheme"
					:accent="accent"
					@go="go"
					@open="openPurchaseOrderId = $event" />

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

				<template v-else-if="tab === 'feedback'">
					<StockDeliveryFeedbackCards v-if="delivery_feedback" :cards="delivery_feedback" portal />
					<div v-else class="space-y-3">
						<div v-for="index in 3" :key="index" class="skeleton h-28 rounded-lg" />
					</div>
				</template>

				<template v-else>
					<SupplierPortalAgreement v-if="tab === 'agreement' && agreement" :agreement="agreement" :preview="preview" />
					<SupplierPortalDetails v-if="loadedDetails && forms" :details="loadedDetails" :forms="forms" :submitted="submitted ?? null" :section="tab as 'products' | 'agreement' | 'grow'" :initial-form-type="formType" :initial-search="productSearch" :labels-print="labels_print" :products-route="products_route" :product-websites-route="product_websites_route" />
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
