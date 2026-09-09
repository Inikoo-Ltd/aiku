<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 10 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, reactive, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserHardHat, faCheck, faSkullCrossbones, faSeedling, faStopwatch } from "@fal"

library.add(faUserHardHat, faCheck, faSkullCrossbones, faSeedling, faStopwatch)

type Tone = "red-deep" | "red" | "orange" | "amber" | "yellow" | "green" | "violet" | "gray"

type CoverBucket = {
	bucket: string
	label: string
	tone: Tone
	count: number
	in_hand: number
	untouched: number
	to_make: number
	stock_value: number
	ranks: { rank: string; count: number; untouched: number }[]
}

type Card = {
	id?: number
	artefact_id: number
	stock_code: string
	stock_name: string
	family: string | null
	maker: string | null
	buyer_code?: string | null
	priority?: string
	health_rank?: string | null
	stock_available: number | null
	days_of_cover?: number | null
	recommended_order_quantity?: number | null
	batch_size?: number | null
	packed_in?: number | null
	job_units?: number | null
	quantity?: number
	job_order_reference?: string | null
	job_order_slug?: string | null
	received_at?: string | null
}

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	leadTime: { days: number; source: "measured" | "estimate"; samples: number }
	coverTotal: number
	coverBuckets: CoverBucket[]
	selectedBuckets: string[]
	toDoLimit: number
	lanes: { to_do: Card[]; queued: Card[]; producing: Card[]; restocked: Card[] }
	toProduceRoute: { name: string; parameters: (string | number)[] }
}>()

const locale = useLocaleStore()

const tonePalette: Record<Tone, { text: string; accent: string; chip: string; border: string }> = {
	"red-deep": {
		text: "text-red-800",
		accent: "bg-red-800",
		chip: "bg-red-100 text-red-800",
		border: "border-t-red-800",
	},
	red: {
		text: "text-red-600",
		accent: "bg-red-500",
		chip: "bg-red-100 text-red-700",
		border: "border-t-red-500",
	},
	orange: {
		text: "text-orange-600",
		accent: "bg-orange-500",
		chip: "bg-orange-100 text-orange-700",
		border: "border-t-orange-500",
	},
	amber: {
		text: "text-amber-600",
		accent: "bg-amber-500",
		chip: "bg-amber-100 text-amber-700",
		border: "border-t-amber-500",
	},
	yellow: {
		text: "text-yellow-600",
		accent: "bg-yellow-400",
		chip: "bg-yellow-100 text-yellow-700",
		border: "border-t-yellow-400",
	},
	green: {
		text: "text-green-700",
		accent: "bg-green-500",
		chip: "bg-green-100 text-green-700",
		border: "border-t-green-500",
	},
	violet: {
		text: "text-violet-700",
		accent: "bg-violet-400",
		chip: "bg-violet-100 text-violet-700",
		border: "border-t-violet-400",
	},
	gray: {
		text: "text-gray-600",
		accent: "bg-gray-300",
		chip: "bg-gray-100 text-gray-600",
		border: "border-t-gray-300",
	},
}

const NEEDS_MAKING = ["out", "w1", "w2", "w3", "w4"]
const passiveIcons: Record<string, string> = {
	ok: "fal fa-check",
	dead: "fal fa-skull-crossbones",
	never: "fal fa-seedling",
}

const needsMaking = computed(() =>
	props.coverBuckets.filter((bucket) => NEEDS_MAKING.includes(bucket.bucket))
)
const notForMaking = computed(() =>
	props.coverBuckets.filter((bucket) => !NEEDS_MAKING.includes(bucket.bucket))
)
const needsMakingTotal = computed(() =>
	needsMaking.value.reduce((total, bucket) => total + bucket.count, 0)
)

function toggleBucket(bucket: string) {
	const next = props.selectedBuckets.includes(bucket)
		? props.selectedBuckets.filter((key) => key !== bucket)
		: [...props.selectedBuckets, bucket]

	router.get(
		route(route().current()!, route().params as any),
		{ buckets: next.join(",") },
		{
			preserveState: true,
			preserveScroll: true,
			replace: true,
			only: ["lanes", "selectedBuckets"],
		}
	)
}

type FilterKey = "family" | "requester" | "priority" | "artisan"
const filters = reactive<Record<FilterKey, string[]>>({
	family: [],
	requester: [],
	priority: [],
	artisan: [],
})

const requesterOf = (card: Card) => card.buyer_code ?? trans("Warehouse")

const allCards = computed(() => [
	...props.lanes.to_do,
	...props.lanes.queued,
	...props.lanes.producing,
	...props.lanes.restocked,
])

const filterOptions = computed(() => {
	const counted = (values: string[]) => {
		const counts: Record<string, number> = {}
		values.filter(Boolean).forEach((value) => (counts[value] = (counts[value] ?? 0) + 1))
		return Object.entries(counts)
			.sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
			.map(([value, count]) => ({ value, count }))
	}

	return {
		family: counted(allCards.value.map((card) => card.family ?? "")),
		requester: counted(allCards.value.map(requesterOf)),
		priority: counted(allCards.value.map((card) => card.priority ?? "")),
		artisan: counted(allCards.value.map((card) => card.maker ?? "")),
	}
})

function toggleFilter(key: FilterKey, value: string) {
	const index = filters[key].indexOf(value)
	index === -1 ? filters[key].push(value) : filters[key].splice(index, 1)
}

const keep = (card: Card) =>
	(!filters.family.length || filters.family.includes(card.family ?? "")) &&
	(!filters.requester.length || filters.requester.includes(requesterOf(card))) &&
	(!filters.priority.length || filters.priority.includes(card.priority ?? "")) &&
	(!filters.artisan.length || filters.artisan.includes(card.maker ?? ""))

const lanes = computed(() => [
	{
		key: "to_do",
		label: trans("To restock"),
		items: props.lanes.to_do.filter(keep),
		tone: "red" as Tone,
	},
	{
		key: "queued",
		label: trans("Queued to produce"),
		items: props.lanes.queued.filter(keep),
		tone: "amber" as Tone,
	},
	{
		key: "producing",
		label: trans("On the floor"),
		items: props.lanes.producing.filter(keep),
		tone: "violet" as Tone,
	},
	{
		key: "restocked",
		label: trans("Back in stock"),
		items: props.lanes.restocked.filter(keep),
		tone: "green" as Tone,
	},
])

const isToDoCapped = (key: string) => key === "to_do" && props.lanes.to_do.length >= props.toDoLimit

const selected = reactive<Record<number, number>>({})

function quantityFor(card: Card): number {
	return Math.max(1, Math.ceil(Number(card.recommended_order_quantity ?? 0)))
}

function toggleCard(card: Card) {
	if (card.artefact_id in selected) {
		delete selected[card.artefact_id]
	} else {
		selected[card.artefact_id] = quantityFor(card)
	}
}

function queue(lines: { artefact_id: number; quantity: number }[]) {
	router.post(
		route("grp.org.productions.show.to_restock.queue", [
			route().params["organisation"],
			route().params["production"],
		]),
		{ lines },
		{
			preserveScroll: true,
			onSuccess: () => {
				for (const key in selected) delete selected[key]
			},
		}
	)
}

const hiddenLanes = ref<string[]>(
	JSON.parse(localStorage.getItem("to-restock-hidden-lanes") || "[]")
)
watch(
	hiddenLanes,
	(value) => localStorage.setItem("to-restock-hidden-lanes", JSON.stringify(value)),
	{ deep: true }
)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div
		v-if="Object.keys(selected).length"
		class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
		<span>{{ Object.keys(selected).length }} {{ trans("artefacts selected") }}</span>
		<button
			type="button"
			class="rounded bg-white px-3 py-1 text-indigo-600"
			@click="
				queue(
					Object.entries(selected).map(([id, quantity]) => ({
						artefact_id: Number(id),
						quantity,
					}))
				)
			">
			{{ trans("Queue to produce") }}
		</button>
	</div>

	<div class="mx-4 mt-4 grid gap-3 sm:grid-cols-2">
		<div
			class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
			<div class="flex items-center gap-2 text-gray-500">
				<FontAwesomeIcon icon="fal fa-stopwatch" fixed-width aria-hidden="true" />
				{{ trans("Lead time") }}
			</div>
			<div class="mt-1">
				<span class="text-2xl font-semibold tabular-nums">{{ leadTime.days }}</span>
				<span class="ml-1 text-gray-500">{{
					trans("days on the floor → back in stock")
				}}</span>
			</div>
			<div class="text-xs text-gray-400">
				{{
					leadTime.source === "measured"
						? trans("measured from :count job orders", { count: leadTime.samples })
						: trans("estimate — no finished job orders to measure yet")
				}}
			</div>
		</div>

		<div
			class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
			<div class="text-gray-500">{{ trans("Stock cover") }}</div>
			<div class="mt-1">
				<span class="text-2xl font-semibold tabular-nums">{{
					locale.number(coverTotal)
				}}</span>
				<span class="ml-1 text-gray-500">{{ trans("artefacts this factory makes") }}</span>
			</div>
			<div class="text-xs text-gray-400">
				{{
					trans("grouped by how long our stock lasts against a :days day lead time", {
						days: leadTime.days,
					})
				}}
			</div>
		</div>
	</div>

	<div class="mx-4 mt-3 text-sm">
		<div
			class="mb-1 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400">
			{{ trans("Needs making") }}
			<span class="rounded-full bg-gray-100 px-1.5 tabular-nums text-gray-600">{{
				locale.number(needsMakingTotal)
			}}</span>
			<span class="normal-case tracking-normal text-gray-400">{{
				trans("click a band to change what the To restock column shows")
			}}</span>
		</div>
		<div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
			<button
				v-for="bucket in needsMaking"
				:key="bucket.bucket"
				type="button"
				class="rounded-lg border border-t-4 bg-white px-3 py-2 text-left transition dark:bg-gray-900"
				:class="[
					tonePalette[bucket.tone].border,
					selectedBuckets.includes(bucket.bucket)
						? 'border-indigo-400 ring-2 ring-indigo-200'
						: 'border-gray-200 hover:border-gray-300 dark:border-gray-700',
				]"
				@click="toggleBucket(bucket.bucket)">
				<div class="flex items-start justify-between gap-2">
					<span
						class="text-2xl font-semibold tabular-nums"
						:class="tonePalette[bucket.tone].text">
						{{ locale.number(bucket.count) }}
					</span>
					<span
						v-if="bucket.untouched"
						class="rounded-full px-1.5 py-0.5 text-xs"
						:class="tonePalette[bucket.tone].chip">
						{{ locale.number(bucket.untouched) }} {{ trans("need action") }}
					</span>
				</div>
				<div class="mt-0.5 text-gray-600 dark:text-gray-300">{{ bucket.label }}</div>
				<div class="mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-400">
					<span
						v-for="rank in bucket.ranks.filter((entry) => entry.count)"
						:key="rank.rank"
						class="rounded bg-gray-100 px-1 dark:bg-gray-800">
						{{ rank.rank }} {{ locale.number(rank.count) }}
					</span>
					<span v-if="bucket.to_make" class="ml-auto tabular-nums"
						>{{ locale.number(Math.round(bucket.to_make)) }}
						{{ trans("to make") }}</span
					>
				</div>
			</button>
		</div>

		<div class="mt-2 grid gap-2 sm:grid-cols-3">
			<button
				v-for="bucket in notForMaking"
				:key="bucket.bucket"
				type="button"
				class="flex items-center gap-3 rounded-lg border bg-white px-3 py-2 text-left transition dark:bg-gray-900"
				:class="
					selectedBuckets.includes(bucket.bucket)
						? 'border-indigo-400 ring-2 ring-indigo-200'
						: 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
				"
				:title="trans('Nothing here needs making — open it anyway to make some')"
				@click="toggleBucket(bucket.bucket)">
				<FontAwesomeIcon
					:icon="passiveIcons[bucket.bucket]"
					class="text-gray-300"
					fixed-width
					aria-hidden="true" />
				<span
					class="text-lg font-semibold tabular-nums"
					:class="tonePalette[bucket.tone].text"
					>{{ locale.number(bucket.count) }}</span
				>
				<span class="text-gray-500">{{ bucket.label }}</span>
			</button>
		</div>
	</div>

	<div class="mx-4 mt-3 text-sm">
		<div
			class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
			<div
				v-for="(label, key) in {
					family: trans('Category'),
					requester: trans('Requester'),
					priority: trans('Urgency'),
					artisan: trans('Artisan'),
				}"
				:key="key"
				class="flex flex-wrap items-center gap-1.5">
				<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{
					label
				}}</span>
				<button
					v-for="option in filterOptions[key]"
					:key="option.value"
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						filters[key].includes(option.value)
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
							: option.value === 'urgent'
								? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
								: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="toggleFilter(key, option.value)">
					<span class="capitalize">{{ option.value }}</span>
					<span
						class="rounded-full px-1.5 text-xs tabular-nums"
						:class="
							filters[key].includes(option.value)
								? 'bg-white/20'
								: 'bg-white text-gray-500'
						">
						{{ option.count }}
					</span>
				</button>
			</div>
			<button
				v-if="Object.values(filters).some((values) => values.length)"
				type="button"
				class="ml-auto text-xs text-gray-400 hover:text-gray-600"
				@click="clearFilters">
				× {{ trans("Clear") }}
			</button>
		</div>
	</div>

	<div class="mx-4 mb-6 mt-3 flex gap-3">
		<div
			v-for="lane in lanes"
			:key="lane.key"
			class="flex min-w-0 flex-1 flex-col rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
			<div class="flex items-center gap-2 px-3 py-2 font-medium">
				<span>{{ lane.label }}</span>
				<Link
					v-if="lane.key === 'queued'"
					:href="route(toProduceRoute.name, toProduceRoute.parameters)"
					class="primaryLink text-xs font-normal">
					{{ trans("board") }}
				</Link>
				<span
					class="ml-auto rounded-full bg-white px-2 text-xs text-gray-500 dark:bg-gray-900"
					:title="
						isToDoCapped(lane.key)
							? trans('the most urgent :count — clear them and more appear', {
									count: toDoLimit,
								})
							: ''
					">
					{{ lane.items.length }}<template v-if="isToDoCapped(lane.key)">+</template>
				</span>
			</div>

			<div class="flex max-h-[70vh] flex-col gap-1.5 overflow-y-auto px-2 pb-2">
				<div
					v-for="card in lane.items"
					:key="lane.key + '-' + (card.id ?? card.artefact_id)"
					class="rounded border bg-white px-2 py-1.5 text-xs dark:bg-gray-900"
					:class="
						lane.key === 'to_do' && card.artefact_id in selected
							? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-950'
							: 'border-gray-200 dark:border-gray-700'
					">
					<div class="flex items-center gap-1.5">
						<span class="font-medium">{{ card.stock_code }}</span>
						<span
							v-if="card.health_rank"
							class="rounded bg-gray-100 px-1 text-gray-500 dark:bg-gray-800"
							>{{ card.health_rank }}</span
						>
						<span
							class="ml-auto tabular-nums"
							:class="card.priority === 'urgent' ? 'font-semibold text-red-600' : ''">
							<template v-if="lane.key === 'to_do'"
								>→ {{ locale.number(quantityFor(card)) }}<span
									v-if="card.job_units"
									class="ml-1 font-normal text-gray-400"
									>{{ locale.number(card.job_units) }}u</span
								></template
							>
							<template v-else-if="card.quantity"
								>×{{ locale.number(Number(card.quantity)) }}</template
							>
						</span>
					</div>
					<div class="truncate text-gray-600" :title="card.stock_name">
						{{ card.stock_name }}
					</div>
					<div class="flex items-center gap-1 text-gray-400">
						<span v-if="card.family">{{ card.family }}</span>
						<Link
							v-if="card.job_order_slug"
							:href="
								route('grp.org.productions.show.operations.job-orders.show', [
									route().params['organisation'],
									route().params['production'],
									card.job_order_slug,
								])
							"
							class="primaryLink ml-auto">
							{{ card.job_order_reference }}
						</Link>
					</div>
					<div class="text-gray-400">
						<span v-if="Number(card.stock_available) > 0"
							>{{ trans("In stock") }}:
							{{ locale.number(Number(card.stock_available)) }}</span
						>
						<span v-else class="text-red-600">{{ trans("Out of stock") }}</span>
						<span v-if="card.days_of_cover != null">
							·
							{{
								trans(":days days cover", {
									days: Math.round(Number(card.days_of_cover)),
								})
							}}</span
						>
					</div>
					<div v-if="card.maker" class="flex items-center gap-1 text-gray-400">
						<FontAwesomeIcon
							icon="fal fa-user-hard-hat"
							fixed-width
							aria-hidden="true" />
						{{ card.maker }}
					</div>
					<button
						v-if="lane.key === 'to_do'"
						type="button"
						class="mt-1 w-full rounded px-2 py-0.5"
						:class="
							card.artefact_id in selected
								? 'bg-indigo-100 text-indigo-700'
								: 'bg-indigo-600 text-white hover:bg-indigo-700'
						"
						@click="toggleCard(card)">
						{{ card.artefact_id in selected ? trans("Selected") : trans("Select") }}
					</button>
				</div>

				<div v-if="!lane.items.length" class="px-2 py-6 text-center text-xs text-gray-400">
					{{ trans("Nothing here") }}
				</div>
			</div>
		</div>
	</div>
</template>
