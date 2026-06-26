<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

const autoPublishingOptions: Array<{ value: boolean; label: string }> = [
    { value: false, label: trans('Immediately') },
    { value: true, label: trans('Delay') },
]

const initialValue = props.form[props.fieldName] ?? {}

const autoPublishingDelay = ref<boolean>(initialValue?.auto_publishing?.delay ?? true)
const autoPublishingDelayHours = ref<number>(initialValue?.auto_publishing?.delay_hours ?? 24)

const syncForm = () => {
    props.form[props.fieldName] = {
        auto_publishing: {
            delay: autoPublishingDelay.value,
            delay_hours: Number(autoPublishingDelayHours.value) || 1,
        },
    }
}

watch([autoPublishingDelay, autoPublishingDelayHours], syncForm)

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="flex flex-col gap-6">

        <div class="flex flex-col gap-2">
            <!-- <span class="text-sm font-semibold text-gray-700">{{ trans('Auto publishing') }}</span> -->
            <div class="flex flex-col gap-2">
                <div
                    v-for="option in autoPublishingOptions"
                    :key="String(option.value)"
                    class="flex items-center gap-2"
                >
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            :value="option.value"
                            v-model="autoPublishingDelay"
                            class="text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm">{{ option.label }}</span>
                    </label>
                    <span
                        v-if="option.value === true && autoPublishingDelay === true"
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
