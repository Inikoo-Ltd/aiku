<script setup lang="ts">
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'

interface OptionItem {
	value: any
	label: string
	icon?: any
}

const props = withDefaults(
	defineProps<{
		options: OptionItem[]
		placeholder?: string
		placement?: string
	}>(),
	{
		placeholder: 'Not set',
		placement: 'bottom-end',
	}
)

const model = defineModel<any>()

const selectedOption = computed(
	() => props.options.find((option) => option.value === model.value) ?? null
)

const isIconOnly = computed(() => props.options.every((option) => option.icon))

const pickOption = (option: OptionItem, hide: () => void) => {
	model.value = option.value
	hide()
}
</script>

<template>
	<VDropdown
		:placement="placement"
		:triggers="['hover', 'focus']"
		:popper-triggers="['hover', 'focus']"
		:delay="{ show: 80, hide: 150 }"
		:distance="4">
		<button
			type="button"
			class="h-7 flex items-center gap-1.5 rounded border border-gray-300 bg-white px-2 text-xs text-gray-700 transition-colors hover:border-gray-400 hover:bg-gray-50">
			<FontAwesomeIcon
				v-if="selectedOption?.icon"
				:icon="selectedOption.icon"
				fixed-width
				aria-hidden="true" />
			<span v-if="!isIconOnly || !selectedOption" class="truncate">
				{{ selectedOption ? trans(selectedOption.label) : trans(placeholder) }}
			</span>
		</button>

		<template #popper="{ hide }">
			<div
				class="flex gap-0.5 p-1"
				:class="isIconOnly ? 'items-center' : 'flex-col min-w-[9rem]'">
				<button
					v-for="option in options"
					:key="String(option.value)"
					type="button"
					v-tooltip.bottom="trans(option.label)"
					@click="pickOption(option, hide)"
					class="h-7 flex items-center gap-1.5 rounded px-2 text-xs transition-colors"
					:class="[
						isIconOnly ? 'justify-center' : 'justify-start text-left',
						option.value === model
							? 'bg-gray-800 text-white'
							: 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
					]">
					<FontAwesomeIcon
						v-if="option.icon"
						:icon="option.icon"
						fixed-width
						aria-hidden="true" />
					<span v-if="!isIconOnly">{{ trans(option.label) }}</span>
				</button>
			</div>
		</template>
	</VDropdown>
</template>
