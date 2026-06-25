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

const autoPublishingOptions = computed<Array<{ value: string; label: string }>>(() =>
    props.fieldData?.options ?? [
        { value: 'immediately', label: trans('Immediately') },
        { value: 'delay', label: trans('Delay') },
        { value: 'never', label: trans('Never') },
    ]
)

const visibilityOptions = computed<Array<{ value: string; label: string }>>(() => [
    { value: 'private', label: trans('Private') },
    { value: 'public', label: trans('Public') },
])

const initialValue = props.form[props.fieldName] ?? {}

const visibilityMode = ref<string>(initialValue?.visibility?.public ? 'public' : 'private')
const autoPublishingMode = ref<string>(initialValue?.auto_publishing?.mode ?? 'immediately')
const autoPublishingDelayHours = ref<number>(initialValue?.auto_publishing?.delay_hours ?? 24)

const syncForm = () => {
    const isPublic = visibilityMode.value === 'public'

    props.form[props.fieldName] = {
        visibility: {
            private: visibilityMode.value === 'private',
            public: isPublic,
        },
        auto_publishing: {
            mode: isPublic ? autoPublishingMode.value : 'immediately',
            delay_hours:
                isPublic && autoPublishingMode.value === 'delay'
                    ? Number(autoPublishingDelayHours.value) || 1
                    : null,
        },
    }
}

watch(
    [visibilityMode, autoPublishingMode, autoPublishingDelayHours],
    syncForm,
    { immediate: true }
)

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">{{ trans('Visibility') }}</label>
            <div class="flex items-center gap-6">
                <label
                    v-for="option in visibilityOptions"
                    :key="option.value"
                    class="flex items-center gap-2 cursor-pointer"
                >
                    <input
                        type="radio"
                        :value="option.value"
                        v-model="visibilityMode"
                        class="text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="text-sm">{{ option.label }}</span>
                </label>
            </div>
            <p class="text-xs text-gray-500">
                {{ trans('Reviews are either private or public by default, not both.') }}
            </p>
        </div>

        <div v-if="visibilityMode === 'public'" class="flex flex-col gap-2">
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
