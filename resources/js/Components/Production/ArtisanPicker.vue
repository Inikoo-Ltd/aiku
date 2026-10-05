<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 04 Oct 2026, Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserHardHat } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faUserHardHat)

type Artisan = { id: number; name: string; open_job_orders: number; hidden: boolean }

const props = defineProps<{
	artisans: Artisan[]
	defaultMaker?: { maker_id?: number | null; maker?: string | null } | null
}>()

const emit = defineEmits<{ (e: "pick", artisanId: number): void }>()

const search = ref("")

function fold(text: string): string {
	return text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase()
}

function fuzzyMatch(needle: string, haystack: string): boolean {
	let index = 0
	for (const char of needle) {
		index = haystack.indexOf(char, index)
		if (index === -1) return false
		index++
	}
	return true
}

function editDistance(a: string, b: string): number {
	const row = Array.from({ length: b.length + 1 }, (_, i) => i)
	for (let i = 1; i <= a.length; i++) {
		let previous = row[0]
		row[0] = i
		for (let j = 1; j <= b.length; j++) {
			const temp = row[j]
			row[j] = Math.min(
				row[j] + 1,
				row[j - 1] + 1,
				previous + (a[i - 1] === b[j - 1] ? 0 : 1)
			)
			previous = temp
		}
	}
	return row[b.length]
}

function nameMatches(needle: string, name: string): boolean {
	const folded = fold(name)
	if (fuzzyMatch(needle, folded)) return true
	if (needle.length < 3) return false
	return folded
		.split(/[\s-]+/)
		.some(
			(word) =>
				editDistance(needle, word.slice(0, needle.length)) <= 1 ||
				editDistance(needle, word) <= 1
		)
}

const isDefault = (artisan: Artisan) =>
	props.defaultMaker?.maker_id
		? artisan.id === props.defaultMaker.maker_id
		: !!props.defaultMaker?.maker && artisan.name === props.defaultMaker.maker

const choices = computed(() => {
	const needle = fold(search.value.trim())
	const visible = props.artisans.filter((artisan) => !artisan.hidden)
	const matched = needle ? visible.filter((artisan) => nameMatches(needle, artisan.name)) : visible
	const list = [...(matched.length ? matched : visible)].sort((a, b) => a.name.localeCompare(b.name))
	return [...list.filter(isDefault), ...list.filter((artisan) => !isDefault(artisan))]
})
</script>

<template>
	<div>
		<input
			v-if="artisans.length > 8"
			v-model="search"
			type="search"
			:placeholder="ctrans('Type a name…')"
			autofocus
			class="mb-1 w-full rounded border-gray-300 py-0.5 text-xs" />
		<div class="flex max-h-64 flex-col gap-0.5 overflow-y-auto">
			<button
				v-for="artisan in choices"
				:key="artisan.id"
				type="button"
				class="flex items-center gap-1.5 rounded px-2 py-1 text-left hover:bg-indigo-50"
				:class="isDefault(artisan) ? 'bg-indigo-50 font-medium text-indigo-700' : ''"
				@click="emit('pick', artisan.id)">
				<FontAwesomeIcon icon="fal fa-user-hard-hat" fixed-width class="text-gray-400" />
				<span class="truncate">{{ artisan.name }}</span>
				<span
					v-if="isDefault(artisan)"
					class="ml-auto text-[10px] uppercase tracking-wide"
					>{{ ctrans("default") }}</span
				>
				<span v-else class="ml-auto text-gray-400">{{ artisan.open_job_orders }}</span>
			</button>
		</div>
	</div>
</template>
