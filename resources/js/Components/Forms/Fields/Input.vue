<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy, faLanguage } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy, faLanguage)
import { ref, watch, computed } from "vue"
import { pendingCompositionUnits } from "@/Composables/usePendingCompositionUnits"
import { trans } from "laravel-vue-i18n"

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        uppercase?: boolean
        additional_instructions?: string
        /** Show the value read-only with an Edit link, instead of a permanently open input */
        collapsible?: boolean
        /** Prefixes the collapsed value with "Nx", for the unit label */
        unitsPreview?: number
        /** Warning shown while editing, before the save confirmation is reached */
        cascadeNote?: string
    }
}>()

const emits = defineEmits()


const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
};

const value = ref(setFormValue(props.form, props.fieldName));
const isEditing = ref(false)

const originalValue = value.value

const cancelEdit = () => {
    value.value = originalValue
    isEditing.value = false
}

watch(() => props.form.recentlySuccessful, (isSuccessful) => {
    if (isSuccessful) {
        isEditing.value = false
    }
})

watch(value, (newValue) => {
    if (props.fieldData?.uppercase && typeof newValue === 'string' && newValue !== newValue.toUpperCase()) {
        value.value = newValue.toUpperCase()
        return
    }

    // Update the form field value when the value ref changes
    updateFormValue(newValue);
    props.form.errors[props.fieldName] = ''
});

const isCollapsible = computed(() => !!props.fieldData?.collapsible || !!props.fieldData?.unitsPreview)
const isCollapsed = computed(() => isCollapsible.value && !isEditing.value)
const collapsedValue = computed(() => {
    const text = value.value || props.fieldData?.placeholder
    return props.fieldData?.unitsPreview ? `${props.fieldData.unitsPreview}x ${text}` : text
})

/*
 * The trade units being edited in their own form imply new units before they are
 * saved; the trade-units field publishes the change through the shared ref.
 */
const pendingUnits = computed(() => {
    if (!props.fieldData?.unitsPreview || !pendingCompositionUnits.value) {
        return null
    }
    return pendingCompositionUnits.value.to
})

const updateFormValue = (newValue) => {
    let target = props.form;
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue);
    } else {
        target[props.fieldName] = newValue;
    }
    emits("update:form", target);
};
</script>
<template>
    <div class="relative">
        <div v-if="isCollapsed" class="flex items-center gap-3">
            <!-- Pink is the sell colour, matching the TU—P edge of the composition triangle -->
            <span :class="pendingUnits ? 'line-through text-gray-400' : 'text-pink-600 font-medium'">
                {{ collapsedValue }}
            </span>
            <span v-if="pendingUnits" class="font-medium text-amber-600">
                → {{ pendingUnits }}x {{ value || fieldData?.placeholder }} ({{ trans('after save') }})
            </span>
            <button type="button" class="text-indigo-600 hover:underline" @click="isEditing = true">
                {{ trans('Edit') }}
            </button>
        </div>

        <div v-else class="relative">
            <PureInput
                v-model="value"
                :inputName="fieldName"
                :readonly="fieldData?.readonly"
                :type="fieldData?.type ?? 'text'"
                :placeholder="fieldData?.placeholder"
                :maxlength="fieldData?.maxLength"
                :copyButton="fieldData?.copyButton"
                :isError="!!form.errors[fieldName]"
                :class="
                    !!form.errors[fieldName] ? 'errorShake' : ''
                "
            >
                <!-- Icon: Error, Success, Loading -->
                <template #stateIcon>
                    <div class="mr-2 h-full flex items-center pointer-events-none">
                        <FontAwesomeIcon v-if="get(form, ['errors', `${fieldName}`])" icon="fas fa-exclamation-circle"
                            class="h-5 w-5 text-red-500" aria-hidden="true" />
                        <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle"
                            class="h-5 w-5 text-green-500" aria-hidden="true" />
                        <!-- <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="h-5 w-5 animate-spin" /> -->
                    </div>
                </template>
            </PureInput>

            <div v-if="isCollapsible" class="flex items-center gap-2 pt-2">
                <!-- Only worth echoing when the preview adds something the input does not show -->
                <span v-if="fieldData?.unitsPreview" class="text-gray-500 mr-1">{{ collapsedValue }}</span>
                <Button :label="trans('Update')" type="save" size="xs"
                    :disabled="form.processing" :loading="form.processing" @click="emits('submit')" />
                <Button :label="trans('Cancel')" type="tertiary" size="xs" @click="cancelEdit" />
            </div>

            <!-- Said before the click, not only in the confirmation dialog -->
            <div v-if="fieldData?.cascadeNote" class="flex items-start gap-1.5 pt-2 text-xs text-amber-700">
                <FontAwesomeIcon icon="fal fa-language" class="mt-0.5" fixed-width aria-hidden="true" />
                <span>{{ fieldData.cascadeNote }}</span>
            </div>
        </div>

        <div v-if="props.fieldData?.additional_instructions" class="text-xs italic text-gray-500 pt-1">
            <span class="text-red-500">*</span> {{ props.fieldData?.additional_instructions }}
        </div>

        <!-- Counter: Letters and Words -->
        <div v-if="props.options?.counter"
            class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
            <p class="">{{ trans('Characters') }}: {{ form[fieldName]?.length ?? 0 }}</p>
            <p class="">
                {{ trans('Words') }}: {{ form[fieldName]?.trim().split(/\s+/).filter(Boolean).length ?? 0 }}
            </p>
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>