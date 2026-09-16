<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"

/**
 * Chips rather than a dropdown: the period is the control that changes most often on a performance
 * page, and chips keep the current one readable without opening anything first.
 *
 * A custom range is a chip like the others that opens two date fields underneath; the fields are the
 * browser's own, which every keyboard and screen reader already knows how to drive. Pages that pass
 * `customRange` get the chip, pages that pass `compare` get the comparison toggle.
 */
const props = withDefaults(
	defineProps<{
		options: Record<string, string>
		selected: string
		param?: string
		label?: string
		customRange?: { from: string; to: string } | null
		compare?: boolean
		comparisonLabel?: string | null
	}>(),
	{ param: "period", label: "" }
)

const CUSTOM = "ctm"

const hasCustom = computed(() => props.customRange !== undefined)
const isCustom = computed(() => props.selected === CUSTOM)

const editingCustom = ref(false)

const isoDate = (date: Date) => date.toISOString().slice(0, 10)

const from = ref(props.customRange?.from ?? isoDate(new Date(Date.now() - 29 * 86400000)))
const to = ref(props.customRange?.to ?? isoDate(new Date()))

const customError = computed(() => {
	if (!from.value || !to.value) return trans("Both dates are needed.")
	if (from.value > to.value) return trans("The start date is after the end date.")

	return null
})

const customLabel = computed(() => {
	if (!props.customRange) return trans("Custom")

	const format = (value: string) =>
		new Date(value + "T00:00:00").toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" })

	return format(props.customRange.from) + " " + trans("to") + " " + format(props.customRange.to)
})

const reload = (data: Record<string, unknown>) => router.reload({ data: { ...data, page: 1 }, preserveScroll: true })

const select = (interval: string) => {
	editingCustom.value = false
	reload({ [props.param]: interval })
}

const openCustom = () => {
	editingCustom.value = !editingCustom.value
}

const applyCustom = () => {
	if (customError.value) return

	editingCustom.value = false
	reload({ [props.param]: CUSTOM, from: from.value, to: to.value })
}

const toggleCompare = (event: Event) =>
	reload({ compare: (event.target as HTMLInputElement).checked ? 1 : 0 })

const chipClass = (active: boolean) =>
	active ? "bg-indigo-600 text-white shadow-sm" : "text-gray-600 hover:bg-gray-100"
</script>

<template>
	<div>
		<div class="flex flex-wrap items-center gap-3">
			<div class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm">
				<span v-if="label" class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ label }}</span>
				<button
					v-for="(optionLabel, interval) in options"
					:key="interval"
					type="button"
					:aria-pressed="interval === selected"
					class="rounded-md px-3 py-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
					:class="chipClass(interval === selected)"
					@click="select(interval as string)">
					{{ optionLabel }}
				</button>
				<button
					v-if="hasCustom"
					type="button"
					:aria-pressed="isCustom"
					:aria-expanded="editingCustom"
					class="rounded-md px-3 py-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
					:class="chipClass(isCustom)"
					@click="openCustom">
					{{ customLabel }}
				</button>
			</div>

			<label v-if="compare !== undefined" class="flex items-center gap-2 text-xs text-gray-600">
				<input
					type="checkbox"
					:checked="compare"
					class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
					@change="toggleCompare" />
				{{ trans("Compare with the period before") }}
				<span v-if="compare && comparisonLabel" class="text-gray-500">({{ comparisonLabel }})</span>
			</label>
		</div>

		<form v-if="editingCustom" class="mt-2 flex flex-wrap items-end gap-2" @submit.prevent="applyCustom">
			<div>
				<label for="period-from" class="block text-xs text-gray-500">{{ trans("From") }}</label>
				<input
					id="period-from"
					v-model="from"
					type="date"
					:max="to"
					class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
			</div>
			<div>
				<label for="period-to" class="block text-xs text-gray-500">{{ trans("To") }}</label>
				<input
					id="period-to"
					v-model="to"
					type="date"
					:min="from"
					class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
			</div>
			<button
				type="submit"
				:disabled="customError !== null"
				class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 disabled:opacity-40">
				{{ trans("Show these dates") }}
			</button>
			<span v-if="customError" class="pb-2 text-xs text-[#a15c00]">{{ customError }}</span>
		</form>
	</div>
</template>
