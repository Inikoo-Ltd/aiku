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
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

type PrePickItem = {
	id: number
	quantity: number
	stock_available: number
	can_pick: number
	stock_code: string
	stock_name: string
	buyer_code: string
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

function toggle(item: PrePickItem) {
	if (item.id in selected) {
		delete selected[item.id]
	} else {
		selected[item.id] = Number(item.can_pick)
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
		pickableRows.value.forEach((row) => (selected[row.id] = Number(row.can_pick)))
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

function prePick(lines: { id: number; quantity: number }[]) {
	router.post(
		route("grp.org.productions.show.to_produce.cherry_pick", [
			route().params["organisation"],
			route().params["production"],
		]),
		{ lines },
		{
			preserveScroll: true,
			onSuccess: () => {
				for (const k in selected) delete selected[k]
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
				class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
				:title="trans('Pre-pick every line listed here, up to what is in stock')"
				@click="prePickAll">
				{{ trans("Pre-pick all") }}
			</button>
		</template>
	</PageHeading>

	<div
		v-if="Object.keys(selected).length"
		class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
		<span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
		<button
			type="button"
			class="rounded bg-white px-3 py-1 text-indigo-600"
			@click="
				prePick(
					Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
				)
			">
			{{ trans("Pre-pick selected") }}
		</button>
	</div>

	<div class="mx-4 mt-4 text-sm">
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
							? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
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
				class="rounded bg-indigo-600 px-2 py-0.5 text-xs text-white hover:bg-indigo-700"
				:title="trans('Take it from stock for this partner')"
				@click="prePick([{ id: item.id, quantity: Number(item.can_pick) }])">
				{{ trans("Pre-pick") }}
			</button>
		</template>
		<template #cell(stock_code)="{ item }: { item: PrePickItem }">
			<div class="font-medium">{{ item.stock_code }}</div>
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
			<span
				class="tabular-nums font-semibold"
				:class="
					Number(item.can_pick) >= Number(item.quantity)
						? 'text-emerald-600'
						: 'text-amber-600'
				"
				:title="
					Number(item.can_pick) >= Number(item.quantity)
						? trans('Everything asked for')
						: trans('Only part of what was asked for')
				">
				{{ useLocaleStore().number(Number(item.can_pick)) }}
			</span>
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
