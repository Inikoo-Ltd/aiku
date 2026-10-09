<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Deferred, Head, Link, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { RouteParams } from "@/types/route-params"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faPeopleArrows,
	faShoppingBasket,
	faTruckContainer,
	faBoxes,
	faClipboardList,
	faWarehouse,
} from "@fal"
library.add(
	faPeopleArrows,
	faShoppingBasket,
	faTruckContainer,
	faBoxes,
	faClipboardList,
	faWarehouse
)

interface CurrentItem {
	type: "agent_order" | "next_container" | "stock_delivery"
	reference: string
	state: string
	state_label: string
	lines: number
	supplier_orders?: number
	value?: number
	date: string
	url: string
}

interface AgentCard {
	id: number
	slug: string
	code: string
	name: string
	status: boolean
	country_code: string | null
	country_name: string | null
	currency_code: string | null
	suppliers: number
	last_submitted_at: string | null
	current: CurrentItem[]
	pipeline: {
		stages: { stage: string; label: string; orders: number; value: number }[]
		late: number
		no_eta: number
	}
}

interface CoverBucket {
	bucket: "out" | "w1" | "w2" | "w3"
	label: string
	count: number
	untouched: number
	bestsellers: number
	lost: number
	lost_untouched: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	currency_code: string
	agents: AgentCard[]
	cover?: Record<number, CoverBucket[]>
}>()

const locale = useLocaleStore()

const wholeMoney = (amount: number) =>
	new Intl.NumberFormat(locale.locale_iso || undefined, {
		style: "currency",
		currency: props.currency_code,
		currencyDisplay: "narrowSymbol",
		maximumFractionDigits: 0,
	}).format(amount)

const organisation = (route().params as RouteParams).organisation

const agentUrl = (agent: AgentCard, routeName = "grp.org.procurement.org_agents.show") =>
	route(routeName, [organisation, agent.slug])

const bucketUrl = (agent: AgentCard, bucket: string) =>
	`${agentUrl(agent, "grp.org.procurement.org_agents.show.shopping.items.index")}?cover=${bucket}`

const bucketsOf = (agent: AgentCard) =>
	(props.cover?.[agent.id] ?? []).filter((bucket) => bucket.count)

const lostOf = (agent: AgentCard, key: "lost" | "lost_untouched") =>
	bucketsOf(agent).reduce((total, bucket) => total + bucket[key], 0)

const shortDate = (date: string) => useFormatTime(date, { formatTime: "d MMM yyyy" })

const bucketShortLabel: Record<string, string> = {
	out: ctrans("Out"),
	w1: ctrans("Doomed"),
	w2: ctrans("Critical"),
	w3: ctrans("Danger"),
}

const bucketClass: Record<string, string> = {
	out: "bg-red-100 text-red-800",
	w1: "bg-rose-50 text-rose-700",
	w2: "bg-orange-50 text-orange-700",
	w3: "bg-amber-50 text-amber-700",
}

const stateClass = (item: CurrentItem) => {
	if (item.state === "in_process") {
		return "bg-amber-100 text-amber-800"
	}
	if (item.state === "dispatched") {
		return "bg-sky-100 text-sky-800"
	}
	if (["received", "checked", "booking_in"].includes(item.state)) {
		return "bg-emerald-100 text-emerald-800"
	}
	return "bg-gray-100 text-gray-700"
}

const ROWS_SHOWN = 5

const groups: { type: CurrentItem["type"]; title: string; empty: string; icon: string }[] = [
	{
		type: "next_container",
		title: ctrans("Next container, at the agent"),
		empty: ctrans("Nothing recorded at the agent yet"),
		icon: "fal fa-warehouse",
	},
	{
		type: "stock_delivery",
		title: ctrans("Containers on the way"),
		empty: ctrans("No containers on the way"),
		icon: "fal fa-truck-container",
	},
]

const openOrdersValue = (agent: AgentCard) =>
	itemsOf(agent, "agent_order").reduce((total, item) => total + (item.value ?? 0), 0)

const readyToShip = (agent: AgentCard) =>
	agent.pipeline.stages.find((stage) => stage.stage === "ready_to_ship")

const itemsOf = (agent: AgentCard, type: CurrentItem["type"]) =>
	agent.current
		.filter((item) => item.type === type)
		.sort(
			(a, b) =>
				Number(b.state === "in_process") - Number(a.state === "in_process") ||
				a.date.localeCompare(b.date)
		)

const expanded = ref<Record<string, boolean>>({})

const isExpanded = (agent: AgentCard, type: string) => !!expanded.value[agent.id + type]

const toggle = (agent: AgentCard, type: string) =>
	(expanded.value[agent.id + type] = !isExpanded(agent, type))

const visibleItems = (agent: AgentCard, type: CurrentItem["type"]) =>
	isExpanded(agent, type) ? itemsOf(agent, type) : itemsOf(agent, type).slice(0, ROWS_SHOWN)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid max-w-[120rem] gap-4 p-4 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
		<div
			v-for="agent in agents"
			:key="agent.id"
			class="flex flex-col rounded-lg border border-gray-200 bg-white shadow-sm"
			:class="{ 'opacity-60': !agent.status }">
			<div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3">
				<img
					v-if="agent.country_code"
					:src="'/flags/' + agent.country_code.toLowerCase() + '.png'"
					:alt="agent.country_name ?? ''"
					:title="agent.country_name ?? ''"
					class="h-4 w-auto shrink-0" />
				<div class="min-w-0 flex-1">
					<Link
						:href="agentUrl(agent)"
						class="block truncate font-semibold text-gray-900 hover:underline"
						>{{ agent.name }}</Link
					>
					<div class="text-xs text-gray-500">
						{{ agent.code }}
						<template v-if="agent.currency_code">· {{ agent.currency_code }}</template>
						·
						<Link
							:href="
								agentUrl(
									agent,
									'grp.org.procurement.org_agents.show.suppliers.index'
								)
							"
							class="hover:text-gray-900 hover:underline">
							{{
								ctrans(":count suppliers", {
									count: locale.number(agent.suppliers),
								})
							}}
						</Link>
					</div>
				</div>
				<span
					v-if="!agent.status"
					class="shrink-0 rounded bg-gray-100 px-1.5 text-xs text-gray-600"
					>{{ ctrans("Inactive") }}</span
				>
			</div>

			<div class="flex-1 space-y-3 px-4 py-3">
				<section v-if="agent.status">
					<Deferred data="cover">
						<template #fallback>
							<div class="h-32 animate-pulse rounded-md bg-gray-100" />
						</template>
						<div
							v-if="bucketsOf(agent).length"
							class="overflow-hidden rounded-md text-xs ring-1 ring-gray-200">
							<div
								v-if="lostOf(agent, 'lost')"
								class="flex items-baseline gap-1.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
								<span class="text-gray-600">{{
									ctrans("Projected lost sales")
								}}</span>
								<span class="font-semibold tabular-nums text-red-700">{{
									wholeMoney(lostOf(agent, "lost"))
								}}</span>
								<span
									v-if="lostOf(agent, 'lost_untouched')"
									class="ml-auto tabular-nums text-gray-500">
									{{
										ctrans(":amount not ordered yet", {
											amount: wholeMoney(lostOf(agent, "lost_untouched")),
										})
									}}
								</span>
							</div>
							<table class="w-full tabular-nums">
								<thead class="text-gray-500">
									<tr>
										<th class="px-2 py-1 text-left font-normal"></th>
										<th class="px-2 py-1 text-right font-normal">
											{{ ctrans("SKOs") }}
										</th>
										<th
											v-tooltip="ctrans('Bestsellers: health rank A or B')"
											class="cursor-help px-2 py-1 text-right font-normal">
											{{ ctrans("A/B") }}
										</th>
										<th class="px-2 py-1 text-right font-normal">
											{{ ctrans("Not ordered") }}
										</th>
										<th class="px-2 py-1 text-right font-normal">
											{{ ctrans("Lost sales") }}
										</th>
									</tr>
								</thead>
								<tbody class="divide-y divide-gray-100">
									<tr
										v-for="bucket in bucketsOf(agent)"
										:key="bucket.bucket"
										class="cursor-pointer hover:bg-gray-50"
										@click="router.visit(bucketUrl(agent, bucket.bucket))">
										<td class="px-2 py-1">
											<span
												v-tooltip="bucket.label"
												class="rounded px-1.5 py-0.5"
												:class="bucketClass[bucket.bucket]"
												>{{ bucketShortLabel[bucket.bucket] }}</span
											>
										</td>
										<td class="px-2 py-1 text-right font-medium text-gray-900">
											{{ locale.number(bucket.count) }}
										</td>
										<td class="px-2 py-1 text-right">
											{{
												bucket.bestsellers
													? locale.number(bucket.bestsellers)
													: ""
											}}
										</td>
										<td class="px-2 py-1 text-right">
											{{
												bucket.untouched
													? locale.number(bucket.untouched)
													: ""
											}}
										</td>
										<td class="px-2 py-1 text-right">
											{{ bucket.lost ? wholeMoney(bucket.lost) : "" }}
										</td>
									</tr>
								</tbody>
							</table>
							<Link
								:href="
									agentUrl(
										agent,
										'grp.org.procurement.org_agents.show.shopping.dashboard'
									)
								"
								class="flex items-center gap-1.5 border-t border-gray-200 px-2 py-1.5 font-medium text-gray-700 hover:bg-gray-50 hover:underline">
								<FontAwesomeIcon icon="fal fa-shopping-basket" fixed-width />
								{{ ctrans("Review and reorder") }}
							</Link>
						</div>
						<div v-else class="text-xs text-emerald-700">
							{{ ctrans("Nothing running out") }}
						</div>
					</Deferred>
				</section>

				<section class="overflow-hidden rounded-md text-xs ring-1 ring-gray-200">
					<Link
						:href="
							agentUrl(
								agent,
								'grp.org.procurement.org_agents.show.agent_orders.index'
							)
						"
						class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-2 py-1.5 hover:bg-gray-100">
						<FontAwesomeIcon
							icon="fal fa-clipboard-list"
							class="text-gray-500"
							fixed-width />
						<span class="font-medium text-gray-600">{{
							ctrans("Open purchase orders")
						}}</span>
						<span class="tabular-nums text-gray-700">{{
							locale.number(itemsOf(agent, "agent_order").length)
						}}</span>
						<span
							v-if="itemsOf(agent, 'agent_order').length"
							class="tabular-nums text-gray-500"
							>· {{ wholeMoney(openOrdersValue(agent)) }}</span
						>
						<span v-if="agent.last_submitted_at" class="ml-auto text-gray-500">
							{{ ctrans("Last order") }}
							<span class="tabular-nums text-gray-700">{{
								shortDate(agent.last_submitted_at)
							}}</span>
						</span>
					</Link>
					<table v-if="agent.pipeline.stages.length" class="w-full tabular-nums">
						<tbody class="divide-y divide-gray-100">
							<tr
								v-for="stage in agent.pipeline.stages.filter(
									(stage) => stage.stage !== 'ready_to_ship'
								)"
								:key="stage.stage">
								<td class="px-2 py-1 text-gray-600">{{ stage.label }}</td>
								<td class="px-2 py-1 text-right text-gray-500">
									{{
										ctrans(":count supplier orders", {
											count: locale.number(stage.orders),
										})
									}}
								</td>
								<td class="px-2 py-1 text-right font-medium text-gray-900">
									{{ wholeMoney(stage.value) }}
								</td>
							</tr>
						</tbody>
					</table>
					<div
						v-if="agent.pipeline.late || agent.pipeline.no_eta"
						class="flex gap-3 border-t border-gray-100 px-2 py-1 text-amber-700">
						<span v-if="agent.pipeline.late">{{
							ctrans(":count past their ETA", {
								count: locale.number(agent.pipeline.late),
							})
						}}</span>
						<span v-if="agent.pipeline.no_eta">{{
							ctrans(":count without ETA", {
								count: locale.number(agent.pipeline.no_eta),
							})
						}}</span>
					</div>
				</section>

				<section
					v-for="group in groups"
					:key="group.type"
					class="overflow-hidden rounded-md ring-1 ring-gray-200">
					<div
						class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-2 py-1.5 text-xs">
						<FontAwesomeIcon :icon="group.icon" class="text-gray-500" fixed-width />
						<span class="font-medium text-gray-600">{{ group.title }}</span>
						<span class="tabular-nums text-gray-500">{{
							locale.number(itemsOf(agent, group.type).length)
						}}</span>
					</div>
					<template v-if="itemsOf(agent, group.type).length">
						<ul class="divide-y divide-gray-100">
							<li
								v-for="item in visibleItems(agent, group.type)"
								:key="item.reference">
								<Link
									:href="item.url"
									class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50">
									<div class="min-w-0 flex-1">
										<div class="truncate font-medium text-gray-900">
											{{ item.reference }}
										</div>
										<div class="text-xs tabular-nums text-gray-500">
											<template
												v-if="
													item.supplier_orders && item.supplier_orders > 1
												">
												{{
													ctrans(":count suppliers", {
														count: item.supplier_orders,
													})
												}}
												·
											</template>
											{{
												item.lines
													? ctrans(":count lines", { count: item.lines })
													: ctrans("empty")
											}}
											<template v-if="item.value !== undefined">
												· {{ wholeMoney(item.value) }}
											</template>
											· {{ shortDate(item.date) }}
										</div>
									</div>
									<span
										class="shrink-0 whitespace-nowrap rounded px-1.5 text-xs"
										:class="stateClass(item)"
										>{{ item.state_label }}</span
									>
								</Link>
							</li>
						</ul>
						<button
							v-if="itemsOf(agent, group.type).length > ROWS_SHOWN"
							type="button"
							class="w-full border-t border-gray-100 px-2 py-1 text-left text-xs text-gray-500 hover:bg-gray-50 hover:text-gray-900"
							@click="toggle(agent, group.type)">
							{{
								isExpanded(agent, group.type)
									? ctrans("Show less")
									: ctrans("Show all :count", {
											count: itemsOf(agent, group.type).length,
										})
							}}
						</button>
					</template>
					<div v-else class="px-2 py-2 text-xs text-gray-500">
						{{ group.empty }}
					</div>
					<div
						v-if="group.type === 'next_container' && readyToShip(agent)"
						class="flex border-t border-gray-100 px-2 py-1.5 text-xs tabular-nums">
						<span class="text-gray-600">{{
							ctrans(":count supplier orders ready to ship", {
								count: locale.number(readyToShip(agent)!.orders),
							})
						}}</span>
						<span class="ml-auto font-medium text-gray-900">{{
							wholeMoney(readyToShip(agent)!.value)
						}}</span>
					</div>
				</section>
			</div>
		</div>

		<p v-if="!agents.length" class="text-gray-500">{{ ctrans("No agents found") }}</p>
	</div>
</template>
