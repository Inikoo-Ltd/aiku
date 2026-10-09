<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Popover from "primevue/popover"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown } from "@far"

library.add(faChevronDown)

interface DownloadOption {
	value: string
	label: string
	is_checked: boolean
}

const props = defineProps<{
	action: {
		label: string
		icon?: string
		style?: string
		tooltip?: string
		target?: string
		route: routeType
		options: DownloadOption[]
	}
}>()

const storageKey = `download-options:${props.action.route.name}`

const readStoredSelection = (): string[] | null => {
	try {
		const stored = JSON.parse(localStorage.getItem(storageKey) ?? "null")
		return Array.isArray(stored) ? stored.filter((value) => props.action.options.some((option) => option.value === value)) : null
	} catch {
		return null
	}
}

const selectedOptions = ref<string[]>(
	readStoredSelection() ?? props.action.options.filter((option) => option.is_checked).map((option) => option.value)
)

watch(selectedOptions, (selection) => {
	try {
		localStorage.setItem(storageKey, JSON.stringify(selection))
	} catch {
		return
	}
})

const optionsPopover = ref()

const href = computed(() =>
	route(props.action.route.name, {
		...(props.action.route.parameters as Record<string, unknown>),
		...Object.fromEntries(selectedOptions.value.map((value) => [value, 1])),
	})
)

const selectedLabels = computed(() =>
	props.action.options.filter((option) => selectedOptions.value.includes(option.value)).map((option) => option.label)
)
</script>

<template>
	<div class="flex items-stretch">
		<a :href="href" :target="action.target ?? '_blank'">
			<Button
				:style="action.style"
				:label="action.label"
				:icon="action.icon"
				:tooltip="selectedLabels.length ? ctrans('Includes :options', { options: selectedLabels.join(', ') }) : action.tooltip"
				class="h-full rounded-r-none text-nowrap"
			/>
		</a>
		<Button
			:style="action.style"
			icon="far fa-chevron-down"
			:tooltip="ctrans('Choose what to include')"
			class="-ml-px h-full rounded-l-none !px-2"
			:class="selectedLabels.length ? 'text-[--app-accent]' : ''"
			@click="(event: Event) => optionsPopover?.toggle(event)"
		/>

		<Popover ref="optionsPopover">
			<div class="flex min-w-48 flex-col gap-2">
				<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Include in the PDF") }}</div>
				<div v-for="option in action.options" :key="option.value" class="flex items-center gap-2">
					<Checkbox v-model="selectedOptions" :inputId="`download-option-${option.value}`" :value="option.value" />
					<label :for="`download-option-${option.value}`" class="cursor-pointer text-sm text-gray-700">{{ option.label }}</label>
				</div>
			</div>
		</Popover>
	</div>
</template>
