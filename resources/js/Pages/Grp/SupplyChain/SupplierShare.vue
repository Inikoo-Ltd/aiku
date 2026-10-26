<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SalesDashboard from "@/Components/Procurement/SalesDashboard.vue"
import type { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt } from "@fal"

library.add(faLock, faEnvelope, faPhone, faUserTie, faMapMarkerAlt)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

interface PurchaseOrderRow {
	reference: string
	organisation: string
	state: string
	state_label: string
	delivery_label: string
	submitted_at: string | null
	confirmed_at: string | null
	expected_at: string | null
	received_at: string | null
	amount: number
	currency: string | null
	lines: number
}

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
}

const props = defineProps<{
	title: string
	supplier: { code: string; name: string }
	company?: Person & { name: string; location: string | null; currency: string | null }
	agent?: (Person & { name: string }) | null
	summary?: Summary
	group: string
	routes: { send_code: string; verify: string }
	needs_code: boolean
	email?: string | null
	code_sent?: boolean
	valuation?: Valuation
	purchase_orders?: PurchaseOrderRow[]
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
const day = (date: string | null) => (date ? useFormatTime(date) : "—")

const welcome = [
	ctrans("We believe good business starts with trust, honesty and straightforward conversations. We take responsibility for our part, respect the people who make our products, and bring generosity, good humour and care to the work we share. We welcome your ideas and want to build a lasting, successful partnership."),
	ctrans("Thank you for your care and attention to products supplied to AW. Together we can sell more of your products."),
]

const openOrders = computed(() => (props.purchase_orders ?? []).filter((order) => order.state !== "settled").length)
const changeLabel = (change: number) =>
	change >= 0 ? ctrans(":percent% more than the 12 months before", { percent: String(change) }) : ctrans(":percent% less than the 12 months before", { percent: String(Math.abs(change)) })
</script>

<template>
	<Head :title="title" />
	<div class="min-h-[100dvh] bg-gray-50">
		<header class="border-b border-gray-200 bg-white px-4 py-3 lg:sticky lg:top-0 lg:z-10">
			<div class="mx-auto max-w-7xl">
				<h1 class="text-lg font-semibold text-gray-800">{{ supplier.name }}</h1>
				<template v-if="!needs_code">
					<p class="mt-1 text-sm font-semibold text-gray-700">
						{{ ctrans("Thank you for trading with Ancient Wisdom.") }}
						<span class="font-normal">{{ ctrans("We are pleased to be your distribution partner in Europe.") }}</span>
					</p>
					<details class="mt-1 text-xs text-gray-600 lg:hidden">
						<summary class="cursor-pointer select-none text-gray-400">{{ ctrans("Read our message") }}</summary>
						<p class="mt-1">{{ welcome[0] }}</p>
						<p class="mt-1 font-semibold text-gray-700">{{ welcome[1] }}</p>
					</details>
					<div class="mt-1 hidden text-xs text-gray-600 lg:block">
						<p>{{ welcome[0] }}</p>
						<p class="mt-1 font-semibold text-gray-700">{{ welcome[1] }}</p>
					</div>
				</template>
				<p v-else class="text-xs text-gray-500">{{ ctrans("How your products sell with :group, and your purchase orders", { group }) }}</p>
			</div>
		</header>

		<main class="mx-auto max-w-7xl space-y-4 p-4">
			<section v-if="needs_code" class="mx-auto mt-10 max-w-md space-y-3 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
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

			<template v-else>
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
					<div v-if="agent" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
						<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Your contact at AW") }}</h2>
						<dl class="mt-2 space-y-1 text-sm text-gray-700">
							<div class="flex items-center gap-1.5 font-medium text-gray-800">
								<FontAwesomeIcon icon="fal fa-user-tie" fixed-width class="text-gray-400" aria-hidden="true" />
								{{ ctrans("Your agent: :agent", { agent: agent.name }) }}<template v-if="agent.contact_name"> — {{ agent.contact_name }}</template>
							</div>
							<div v-if="agent.email" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-envelope" fixed-width class="text-gray-400" aria-hidden="true" /><a :href="`mailto:${agent.email}`" class="hover:underline">{{ agent.email }}</a></div>
							<div v-if="agent.phone" class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />{{ agent.phone }}</div>
						</dl>
					</div>
				</section>

				<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
							<p class="mt-2 text-xs text-gray-500">{{ ctrans("Against every AW supplier based in your country with sales in the same 12 months, with an agent or without, each at its own prices. Other suppliers are never named.") }}</p>
						</template>
						<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
					</div>

					<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
						<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Products listed") }}</h2>
						<template v-if="sales">
							<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ sales.products }}</p>
							<p class="text-xs text-gray-600">{{ ctrans(":count sold in the last 12 months", { count: String(sales.selling) }) }}</p>
						</template>
						<div v-else class="mt-2 h-14 animate-pulse rounded bg-gray-100" />
					</div>

					<a href="#purchase-orders" class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300">
						<h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Open purchase orders") }}</h2>
						<p class="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{{ openOrders }}</p>
						<p class="text-xs text-gray-600">{{ ctrans("Sent to you and not received yet") }}</p>
					</a>
				</section>

				<SalesDashboard v-if="valuation" seller="supplier" :staff="false" :links="null" :valuation="valuation" :sales="sales" :feedback="feedback" />

				<section id="purchase-orders" class="scroll-mt-40 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Your purchase orders") }}</h2>
					<p class="mt-0.5 text-xs text-gray-500">{{ ctrans("Orders sent to you and not closed yet, and the ones received in the last 60 days") }}</p>
					<p v-if="!purchase_orders?.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No open purchase orders") }}</p>
					<div v-else class="mt-3 overflow-x-auto">
						<table class="w-full text-sm">
							<thead class="text-xs uppercase tracking-wide text-gray-400">
								<tr>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Order") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Ordered by") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Status") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Sent") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Confirmed") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Expected") }}</th>
									<th class="whitespace-nowrap pb-1 text-left font-medium">{{ ctrans("Received") }}</th>
									<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Lines") }}</th>
									<th class="whitespace-nowrap pb-1 text-right font-medium">{{ ctrans("Amount") }}</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100 tabular-nums text-gray-700">
								<tr v-for="order in purchase_orders" :key="order.reference">
									<td class="whitespace-nowrap py-1.5 font-medium text-gray-800">{{ order.reference }}</td>
									<td class="whitespace-nowrap py-1.5">{{ order.organisation }}</td>
									<td class="whitespace-nowrap py-1.5">
										{{ order.state_label }}
										<span v-if="order.state !== 'settled'" class="text-xs text-gray-400"> · {{ order.delivery_label }}</span>
									</td>
									<td class="whitespace-nowrap py-1.5">{{ day(order.submitted_at) }}</td>
									<td class="whitespace-nowrap py-1.5">{{ day(order.confirmed_at) }}</td>
									<td class="whitespace-nowrap py-1.5">{{ day(order.expected_at) }}</td>
									<td class="whitespace-nowrap py-1.5">{{ day(order.received_at) }}</td>
									<td class="whitespace-nowrap py-1.5 text-right">{{ order.lines }}</td>
									<td class="whitespace-nowrap py-1.5 text-right">{{ money(order.amount, order.currency) }}</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>
			</template>
		</main>
	</div>
</template>
