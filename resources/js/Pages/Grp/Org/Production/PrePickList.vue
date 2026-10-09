<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 09 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, reactive, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans as trans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

type PrePickItem = {
	id: number
	quantity: number
	stock_available: number
	can_pick: number
	shortfall: number
	automation_status: "releasing" | "held_buffer" | "awaiting_full_stock" | "to_produce"
	stock_code: string
	stock_name: string
	is_cosmetic: boolean
	is_gb: boolean
	buyer_code: string
	to_location: string | null
	priority: string
}

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	data: { data: PrePickItem[] }
	filters: Record<
		string,
		{ label: string; options: { value: string; label: string; count: number }[] }
	>
}>()

const selected = reactive<Record<number, number>>({})
const quantities = reactive<Record<number, number>>({})

const quantityFor = (item: PrePickItem) => quantities[item.id] ?? Number(item.can_pick)

const isPartial = (item: PrePickItem) => quantityFor(item) < Number(item.quantity)

const automationStatuses = {
	releasing: { label: trans("Going to the warehouse"), class: "text-emerald-600" },
	held_buffer: { label: trans("Held, one more needed on the shelf"), class: "text-amber-600" },
	awaiting_full_stock: { label: trans("Awaiting full stock, production manager to decide"), class: "text-amber-600" },
	to_produce: { label: trans("In To produce"), class: "text-gray-500" },
}

function setQuantity(item: PrePickItem, value: string) {
	const quantity = Math.min(Math.max(Number(value) || 0, 0), Number(item.can_pick))
	quantities[item.id] = quantity
	if (item.id in selected) {
		selected[item.id] = quantity
	}
}

function toggle(item: PrePickItem) {
	if (item.id in selected) {
		delete selected[item.id]
	} else {
		selected[item.id] = quantityFor(item)
	}
}

const pickableRows = computed(() => props.data.data)
const allSelected = computed(
	() => pickableRows.value.length > 0 && pickableRows.value.every((row) => row.id in selected)
)

function toggleAll() {
	if (allSelected.value) {
		for (const k in selected) delete selected[k]
	} else {
		pickableRows.value.forEach((row) => (selected[row.id] = quantityFor(row)))
	}
}

function filtersFromUrl(): Record<string, string[]> {
	const params = new URLSearchParams(window.location.search)
	return Object.fromEntries(
		Object.keys(props.filters).map((key) => {
			const value = params.get(`elements[${key}]`)
			return [key, value ? value.split(",") : []]
		})
	)
}

const activeFilters = ref<Record<string, string[]>>(filtersFromUrl())

function applyFilters(next: Record<string, string[]>) {
	activeFilters.value = { ...activeFilters.value, ...next }
	const params = new URLSearchParams(window.location.search)
	Object.entries(activeFilters.value).forEach(([key, values]) => {
		values.length
			? params.set(`elements[${key}]`, values.join(","))
			: params.delete(`elements[${key}]`)
	})
	router.get(
		`${window.location.pathname}?${params}`,
		{},
		{ preserveState: true, preserveScroll: true, replace: true }
	)
}

function toggleFilter(key: string, value: string) {
	const current = activeFilters.value[key]
	applyFilters({
		[key]: current.includes(value) ? current.filter((v) => v !== value) : [...current, value],
	})
}

function clearFilters() {
	applyFilters(Object.fromEntries(Object.keys(props.filters).map((key) => [key, []])))
}

function prePickAll() {
	router.post(
		`${route("grp.org.productions.show.pre_pick.all", [route().params["organisation"], route().params["production"]])}${window.location.search}`,
		{},
		{
			preserveScroll: true,
			onSuccess: () => {
				for (const k in selected) delete selected[k]
			},
		}
	)
}

function closeShort(item: PrePickItem) {
	const quantity = quantityFor(item)
	const message = trans(
		"Send :quantity and cancel the other :shortfall? The partner's list drops to :quantity and the rest will not be made.",
		{
			quantity: useLocaleStore().number(quantity),
			shortfall: useLocaleStore().number(Number(item.quantity) - quantity),
		}
	)
	if (window.confirm(message)) {
		prePick([{ id: item.id, quantity, cancel_shortfall: true }])
	}
}

function prePick(lines: { id: number; quantity: number; cancel_shortfall?: boolean }[]) {
	router.post(
		route("grp.org.productions.show.pre_pick.pick", [
			route().params["organisation"],
			route().params["production"],
		]),
		{ lines },
		{
			preserveScroll: true,
			onSuccess: () => {
				for (const k in selected) delete selected[k]
				for (const k in quantities) delete quantities[k]
			},
		}
	)
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #otherBefore>
			<button
				type="button"
				class="rounded bg-[--app-accent] px-3 py-1.5 text-sm font-medium text-[--app-accent-text] hover:bg-[--app-accent-strong]"
				:title="trans('Reserve every line listed here, up to what is in stock')"
				@click="prePickAll">
				{{ trans("Pre-pick all") }}
			</button>
		</template>
	</PageHeading>

	<div
		v-if="Object.keys(selected).length"
		class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-[--app-accent] px-4 py-2 text-[--app-accent-text]">
		<span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
		<button
			type="button"
			class="rounded bg-white px-3 py-1 text-[--app-accent]"
			@click="
				prePick(
					Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
				)
			">
			{{ trans("Pre-pick selected") }}
		</button>
	</div>

	<div class="mx-4 mt-3 text-xs text-gray-500">
		{{
			trans(
				"Lines go to the warehouse on their own, whole, when the shelf holds more than they ask. What is missing goes to To produce."
			)
		}}
	</div>

	<div class="mx-4 mt-3 text-sm">
		<div
			class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900">
			<div
				v-for="(group, key) in filters"
				:key="key"
				class="flex flex-wrap items-center gap-1.5">
				<span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{
					group.label
				}}</span>
				<button
					v-for="option in group.options"
					:key="option.value"
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
					:class="
						activeFilters[key].includes(option.value)
							? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm'
							: option.value === 'urgent'
								? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
								: 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'
					"
					@click="toggleFilter(key, option.value)">
					<span class="capitalize">{{ option.label }}</span>
					<span
						class="rounded-full px-1.5 text-xs tabular-nums"
						:class="
							activeFilters[key].includes(option.value)
								? 'bg-white/20'
								: 'bg-white text-gray-500'
						"
						>{{ option.count }}</span
					>
				</button>
			</div>
			<button
				v-if="Object.values(activeFilters).some((values) => values.length)"
				type="button"
				class="text-xs text-gray-400 hover:text-gray-600"
				@click="clearFilters">
				× {{ trans("Clear") }}
			</button>
		</div>
	</div>

	<Table :resource="data" class="mt-3">
		<template #header(pick)>
			<th scope="col" class="px-2 text-left font-normal lg:px-6">
				<input
					type="checkbox"
					class="align-middle"
					:checked="allSelected"
					:title="trans('Select every line on this page')"
					@change="toggleAll" />
			</th>
		</template>
		<template #cell(pick)="{ item }: { item: PrePickItem }">
			<input
				type="checkbox"
				class="align-middle"
				:checked="item.id in selected"
				@change="toggle(item)" />
		</template>
		<template #cell(action)="{ item }: { item: PrePickItem }">
			<button
				type="button"
				class="rounded bg-[--app-accent] px-2 py-0.5 text-xs text-[--app-accent-text] hover:bg-[--app-accent-strong] disabled:cursor-not-allowed disabled:bg-gray-300"
				:disabled="Number(item.can_pick) <= 0"
				:title="
					Number(item.can_pick) > 0
						? trans('Reserve it for this partner and send it to their bay')
						: trans('All the stock on the shelf is already promised to earlier pre-picks')
				"
				@click="prePick([{ id: item.id, quantity: quantityFor(item) }])">
				{{ trans("Pre-pick") }}
			</button>
			<button
				v-if="isPartial(item) && quantityFor(item) > 0"
				type="button"
				class="ml-1 rounded border border-amber-500 px-2 py-0.5 text-xs text-amber-700 hover:bg-amber-50"
				:title="trans('Pre-pick this quantity and cancel the shortfall, nothing stays outstanding')"
				@click="closeShort(item)">
				{{ trans("Close short") }}
			</button>
		</template>
		<template #cell(stock_code)="{ item }: { item: PrePickItem }">
			<div class="flex items-center gap-1.5 font-medium">
				{{ item.stock_code }}
				<span
					v-if="item.is_cosmetic"
					class="rounded-full bg-pink-100 px-1.5 text-xs font-normal text-pink-700"
					>{{ trans("Cosmetic") }}</span
				>
				<span
					v-if="item.is_gb"
					v-tooltip="trans('GB-origin, travels on the separate GB pallet')"
					class="rounded-full bg-slate-200 px-1.5 text-xs font-normal text-slate-700"
					>GB</span
				>
			</div>
			<div class="text-xs text-gray-500">{{ item.stock_name }}</div>
		</template>
		<template #cell(quantity)="{ item }: { item: PrePickItem }">
			<span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
		</template>
		<template #cell(stock_available)="{ item }: { item: PrePickItem }">
			<span class="tabular-nums">{{
				useLocaleStore().number(Number(item.stock_available))
			}}</span>
		</template>
		<template #cell(can_pick)="{ item }: { item: PrePickItem }">
			<input
				type="number"
				min="0"
				:max="Number(item.can_pick)"
				step="any"
				class="w-20 rounded border-gray-300 px-2 py-0.5 text-right text-sm tabular-nums font-semibold"
				:class="
					quantityFor(item) >= Number(item.quantity)
						? 'text-emerald-600'
						: 'text-amber-600'
				"
				:title="
					trans('Up to :quantity can be sent; what is not sent stays on the list', {
						quantity: useLocaleStore().number(Number(item.can_pick)),
					})
				"
				:value="quantityFor(item)"
				@change="setQuantity(item, ($event.target as HTMLInputElement).value)" />
			<div
				v-if="isPartial(item)"
				class="mt-0.5 text-[11px] text-amber-600"
				:title="trans('The warehouse will pick this line in two goes')">
				{{ trans("partial pick") }}
			</div>
		</template>
		<template #cell(shortfall)="{ item }: { item: PrePickItem }">
			<span
				class="tabular-nums"
				:class="Number(item.shortfall) > 0 ? 'font-semibold text-amber-600' : 'text-gray-400'"
				>{{ useLocaleStore().number(Number(item.shortfall)) }}</span
			>
		</template>
		<template #cell(automation_status)="{ item }: { item: PrePickItem }">
			<span :class="automationStatuses[item.automation_status].class">{{
				automationStatuses[item.automation_status].label
			}}</span>
		</template>
		<template #cell(priority)="{ item }: { item: PrePickItem }">
			<span
				:class="item.priority === 'urgent' ? 'font-semibold text-red-600' : 'text-gray-500'"
				>{{ item.priority }}</span
			>
		</template>
		<template #cell(created_at)="{ item }: { item: { created_at: string } }">
			{{ useFormatTime(item.created_at) }}
		</template>
	</Table>
</template>
