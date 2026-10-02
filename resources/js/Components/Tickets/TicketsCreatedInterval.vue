<script setup lang="ts">
import { onMounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

const props = withDefaults(
	defineProps<{
		options: Record<string, string>
		selected: string
		storageKey?: string
		label?: string
		param?: string
		compact?: boolean
		only?: string[]
	}>(),
	{ storageKey: "tickets-created-interval", label: "Created", param: "created", only: () => [] }
)

const storageKey = props.storageKey
const loadingInterval = ref<string | null>(null)

const select = (interval: string) => {
	try {
		localStorage.setItem(storageKey, interval)
	} catch {}
	router.reload({
		data: { [props.param]: interval, page: 1 },
		only: props.only,
		preserveScroll: true,
		onStart: () => (loadingInterval.value = interval),
		onFinish: () => (loadingInterval.value = null),
	})
}

onMounted(() => {
	if (new URLSearchParams(window.location.search).has(props.param)) {
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
		router.reload({ data: { [props.param]: remembered, page: 1 }, only: props.only, preserveScroll: true })
	}
})
</script>

<template>
	<div
		class="flex flex-wrap items-center gap-1 rounded-lg border border-gray-200 bg-white"
		:class="compact ? 'px-1 py-0.5 text-xs' : 'px-2 py-1.5 text-sm'">
		<span v-if="label" class="mr-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{
			ctrans(label)
		}}</span>
		<button
			v-for="(optionLabel, interval) in options"
			:key="interval"
			type="button"
			class="inline-flex items-center gap-1.5 rounded-md transition"
			:class="[
				compact ? 'px-2 py-0.5' : 'px-3 py-1',
				interval === selected
					? 'bg-[--app-accent] text-[--app-accent-text] shadow-sm'
					: 'text-gray-600 hover:bg-gray-100'
			]"
			:disabled="loadingInterval !== null"
			@click="select(interval)">
			<LoadingIcon v-if="loadingInterval === interval" />
			{{ optionLabel }}
		</button>
	</div>
</template>
