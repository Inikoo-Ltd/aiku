<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"

defineProps<{
	options: Record<string, string>
	selected: string
}>()

const select = (interval: string) =>
	router.reload({ data: { created: interval, page: 1 }, preserveScroll: true })
</script>

<template>
	<div class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm">
		<span class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Created") }}</span>
		<button
			v-for="(label, interval) in options"
			:key="interval"
			type="button"
			class="rounded-md px-3 py-1 transition"
			:class="interval === selected ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
			@click="select(interval)">
			{{ label }}
		</button>
	</div>
</template>
