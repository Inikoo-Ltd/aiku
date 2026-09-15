<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"

/**
 * Chips rather than a dropdown: the period is the control that changes most often on a performance
 * page, and chips keep the current one readable without opening anything first.
 */
withDefaults(
	defineProps<{
		options: Record<string, string>
		selected: string
		param?: string
		label?: string
	}>(),
	{ param: "period", label: "" }
)

const select = (interval: string, param: string) =>
	router.reload({ data: { [param]: interval, page: 1 }, preserveScroll: true })
</script>

<template>
	<div class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm">
		<span v-if="label" class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ label }}</span>
		<button
			v-for="(optionLabel, interval) in options"
			:key="interval"
			type="button"
			:aria-pressed="interval === selected"
			class="rounded-md px-3 py-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1"
			:class="interval === selected ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
			@click="select(interval as string, param)">
			{{ optionLabel }}
		</button>
	</div>
</template>
