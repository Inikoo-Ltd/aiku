<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 May 2023 09:18:00 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Multiselect from "@vueform/multiselect"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core"
import { onMounted } from "vue"
import Tag from '@/Components/Tag.vue'
library.add(faExclamationCircle, faCheckCircle)

const props = defineProps<{
    form: any
    fieldName: any
    options: string[] | {label?: string, value: string}[]
    fieldData: {
        placeholder?: string
        required?: boolean
        mode?: "multiple" | "single" | "tags"
		searchable?: boolean
        readonly?: boolean
		labelProp?: string
		valueProp?: string
    }
}>()

// Auto assign to first option if 'required' and value is null
onMounted(() => {
    if(props.fieldData?.required && !props.form[props.fieldName]) {
        props.form[props.fieldName] = props.options?.[0]?.value
    }
})
</script>

<template>
	<div class="">
		<div class="relative"
            :class="form.errors[fieldName] ? 'errorShake' : ''"
        >
			<Multiselect
				v-model="form[fieldName]"
                @update:modelValue="() => form.errors[fieldName] = null"
				:class="{ 'pr-8': form.errors[fieldName] || form.recentlySuccessful }"
				:options="props.options"
				:placeholder="props.fieldData.placeholder ?? 'Select your option'"
				:canClear="!props.fieldData.required"
				:mode="props.fieldData.mode ? props.fieldData.mode : 'single'"
				:closeOnSelect="props.fieldData.mode == 'multiple' ? false : true"
				:canDeselect="!props.fieldData.required"
				:hideSelected="false"
                :disabled="fieldData.readonly"
                :caret="!fieldData.readonly"
				:searchable="!!props.fieldData.searchable" 
				:label="fieldData.labelProp || 'label'"
				:valueProp="fieldData.valueProp || 'value'"
				>
				<template
					#tag="{ option, handleTagRemove, disabled }: { option: tag, handleTagRemove: Function, disabled: boolean }">
					<div class="px-0.5 py-[3px]">
						<Tag :theme="option.id" :label="option[fieldData.labelProp].name" :closeButton="true"
							:stringToColor="true" size="sm"
							@onClose="(event) => handleTagRemove(option, event)" />
					</div>
				</template>
			</Multiselect>
			<div
				v-if="form.errors[fieldName] || form.recentlySuccessful"
				class="absolute inset-y-2/4 right-0 pr-3 flex items-center pointer-events-none bg-red-500">
				<FontAwesomeIcon
					icon="fas fa-exclamation-circle"
					v-if="form.errors[fieldName]"
					class="h-5 w-5 text-red-500"
					aria-hidden="true" />
				<FontAwesomeIcon
					icon="fas fa-check-circle"
					v-if="form.recentlySuccessful"
					class="mt-1.5 h-5 w-5 text-green-500"
					aria-hidden="true" />
			</div>
		</div>
        
		<p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="email-error">
			{{ form.errors[fieldName] }}
		</p>
	</div>
</template>

<style src="@vueform/multiselect/themes/default.css"></style>

<style scoped>
/* Style for multiselect globally */
:deep(.multiselect-option.is-selected),
:deep(.multiselect-option.is-selected.is-pointed) {
	background: var(--ms-option-bg-selected, #6366f1) !important;
	color: var(--ms-option-color-selected, #fff) !important;
}

:deep(.multiselect-option.is-selected.is-disabled) {
	background: var(--ms-option-bg-selected-disabled, #c7d2fe);
	color: var(--ms-option-color-selected-disabled, #818cf8);
}

:deep(.multiselect.is-active) {
	border: var(--ms-border-width-active, var(--ms-border-width, 1px)) solid
		var(--ms-border-color-active, var(--ms-border-color, #d1d5db));
	box-shadow: 0 0 0 var(--ms-ring-width, 3px) var(--ms-ring-color, rgba(99, 102, 241, 0.188));
}
</style>
