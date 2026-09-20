<script setup lang="ts">
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
	options: Record<string, string>
	selected: string
}>()

const storageKey = "tickets-created-interval"

const select = (interval: string) => {
	try {
		localStorage.setItem(storageKey, interval)
	} catch {}
	router.reload({ data: { created: interval, page: 1 }, preserveScroll: true })
}

onMounted(() => {
	if (new URLSearchParams(window.location.search).has("created")) {
		try {
			localStorage.setItem(storageKey, props.selected)
		} catch {}
		return
	}
	let remembered: string | null = null
	try {
		remembered = localStorage.getItem(storageKey)
	} catch {}
	if (remembered && remembered !== props.selected && remembered in props.options) {
		router.reload({ data: { created: remembered, page: 1 }, preserveScroll: true })
	}
})
</script>

<template>
	<div class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm">
		<span class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Created") }}</span>
		<button
			v-for="(label, interval) in options"
			:key="interval"
			type="button"
			class="rounded-md px-3 py-1 transition"
			:class="interval === selected ? 'bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'text-gray-600 hover:bg-gray-100'"
			@click="select(interval)">
			{{ label }}
		</button>
	</div>
</template>
