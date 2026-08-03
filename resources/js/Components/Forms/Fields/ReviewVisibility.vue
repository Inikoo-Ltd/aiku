<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { get } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import Toggle from '@/Components/Pure/Toggle.vue'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

const initialValue = props.form[props.fieldName] ?? {}

const visibilityPrivate = ref<boolean>(initialValue?.visibility?.private ?? false)
const visibilityPublic = ref<boolean>(initialValue?.visibility?.public ?? true)

const syncForm = () => {
    props.form[props.fieldName] = {
        visibility: {
            private: visibilityPrivate.value,
            public: visibilityPublic.value,
        },
    }
}

watch([visibilityPrivate, visibilityPublic], syncForm)

const fieldNameString = computed(() => props.fieldName)
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
                <Toggle v-model="visibilityPrivate" :classes="visibilityPrivate ? '!bg-indigo-500' : ''" />
                <span class="text-sm">{{ trans('Private') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <Toggle v-model="visibilityPublic" :classes="visibilityPublic ? '!bg-indigo-500' : ''" />
                <span class="text-sm">{{ trans('Public') }}</span>
            </div>
        </div>
        <p class="text-xs text-gray-500">
            {{ trans('You can enable one or both visibility modes for reviews.') }}
        </p>

        <p
            v-if="get(form, ['errors', fieldNameString])"
            class="text-sm text-red-600"
            :id="`${fieldNameString}-error`"
        >
            {{ form.errors[fieldNameString] }}
        </p>
    </div>
</template>
