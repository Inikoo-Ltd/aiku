<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import ToggleSwitch from 'primevue/toggleswitch'
import PureInput from '@/Components/Pure/PureInput.vue'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

const autoPublishingOptions = computed<Array<{ value: string; label: string }>>(() =>
    props.fieldData?.options ?? [
        { value: 'immediately', label: trans('Immediately') },
        { value: 'delay', label: trans('Delay') },
        { value: 'never', label: trans('Never') },
    ]
)

const initialValue = props.form[props.fieldName] ?? {}

const visibilityPrivate = ref<boolean>(initialValue?.visibility?.private ?? true)
const visibilityPublic = ref<boolean>(initialValue?.visibility?.public ?? true)
const autoPublishingMode = ref<string>(initialValue?.auto_publishing?.mode ?? 'immediately')
const autoPublishingDelayHours = ref<number>(initialValue?.auto_publishing?.delay_hours ?? 24)

const syncForm = () => {
    props.form[props.fieldName] = {
        visibility: {
            private: visibilityPrivate.value,
            public: visibilityPublic.value,
        },
        auto_publishing: {
            mode: autoPublishingMode.value,
            delay_hours: Number(autoPublishingDelayHours.value) || 1,
        },
    }
}

watch(
    [visibilityPrivate, visibilityPublic, autoPublishingMode, autoPublishingDelayHours],
    syncForm
)

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">{{ trans('Visibility') }}</label>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="visibilityPrivate" />
                    <span class="text-sm">{{ trans('Private') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="visibilityPublic" />
                    <span class="text-sm">{{ trans('Public') }}</span>
                </div>
            </div>
            <p class="text-xs text-gray-500">
                {{ trans('You can enable one or both visibility modes for reviews.') }}
            </p>
        </div>

        <div v-if="visibilityPublic" class="flex flex-col gap-2">
            <label class="text-sm font-medium">{{ trans('Auto publishing') }}</label>
            <div class="flex flex-col gap-2">
                <div
                    v-for="option in autoPublishingOptions"
                    :key="option.value"
                    class="flex items-center gap-2"
                >
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            :value="option.value"
                            v-model="autoPublishingMode"
                            class="text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm">{{ option.label }}</span>
                    </label>
                    <span
                        v-if="option.value === 'delay' && autoPublishingMode === 'delay'"
                        class="ml-2 flex items-center gap-2"
                    >
                        <PureInput
                            type="number"
                            minValue="1"
                            class="w-24"
                            :modelValue="autoPublishingDelayHours"
                            @update:modelValue="autoPublishingDelayHours = Number($event)"
                        />
                        <span class="text-sm text-gray-500">{{ trans('hours') }}</span>
                    </span>
                </div>
            </div>
        </div>

        <p
            v-if="get(form, ['errors', fieldNameString])"
            class="text-sm text-red-600"
            :id="`${fieldNameString}-error`"
        >
            {{ form.errors[fieldNameString] }}
        </p>
    </div>
</template>
