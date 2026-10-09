<script setup lang="ts">
import { watch } from "vue"
import Select from "primevue/select"
import Tag from "@/Components/Tag.vue"
import { ctrans } from "@/Composables/useTrans"
import { coveredByExplanation } from "@/Composables/useLeaveCovers"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"

export interface LeaveCoverOption {
	value: string
	label: string
	disabled: boolean
	lacksGroupAccess: boolean
	sharedRoles: string[]
	sharedDepartments: string[]
	tooltip: string | null
}

const props = defineProps<{
	options: LeaveCoverOption[]
	error?: string
	hint?: string
}>()

const model = defineModel<string | number>({ default: "" })

watch(
	() => props.options,
	(options) => {
		if (options.some((option) => option.disabled && option.value === String(model.value))) {
			model.value = ""
		}
	}
)
</script>

<template>
	<div>
		<label class="flex items-center gap-1 text-sm font-medium text-gray-700">
			{{ ctrans("Covered by") }}
			<FontAwesomeIcon v-tooltip="coveredByExplanation()" :icon="faInfoCircle" class="text-gray-400 hover:text-gray-600" fixed-width :aria-label="coveredByExplanation()" />
		</label>
		<Select
			v-model="model"
			:options="options"
			optionLabel="label"
			optionValue="value"
			optionDisabled="disabled"
			filter
			showClear
			:placeholder="ctrans('Nobody')"
			class="mt-1 w-full">
			<template #option="{ option }">
				<div v-tooltip="option.tooltip" class="flex w-full items-center justify-between gap-2">
					<span>{{ option.label }}</span>
					<Tag v-if="option.disabled" size="xxs" :label="ctrans('On leave')" />
					<Tag v-else-if="option.lacksGroupAccess" :theme="7" size="xxs" :label="ctrans('Not recommended')" />
					<Tag v-else-if="option.sharedRoles.length" :theme="3" size="xxs" :label="ctrans('Highly recommended')" />
					<Tag v-else-if="option.sharedDepartments.length" :theme="1" size="xxs" :label="ctrans('Recommended')" />
				</div>
			</template>
		</Select>
		<p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
		<p v-if="model" class="mt-2 text-sm text-gray-500">
			{{ hint ?? ctrans("The cover gets this employee's permissions until the leave ends") }}
		</p>
	</div>
</template>
