<script setup lang="ts">
import { computed } from 'vue'
import { get, set } from 'lodash-es'
import InputText from 'primevue/inputtext'

const props = defineProps<{
    form: any
    fieldName: string | string[]
}>()

const contexts = [
    { key: 'product_reviews', label: 'Product Reviews' },
    { key: 'shop_reviews', label: 'Shop Reviews' },
    { key: 'product_category_reviews', label: 'Product Category Reviews' },
]

const dimensions = ['a', 'b', 'c', 'd', 'e']

const formKey = computed(() => {
    return Array.isArray(props.fieldName) ? props.fieldName[0] : props.fieldName
})

const ensureValue = () => {
    if (!props.form[formKey.value] || typeof props.form[formKey.value] !== 'object') {
        props.form[formKey.value] = {}
    }
}

const getLabelValue = (context: string, dimension: string): string => {
    ensureValue()
    return get(props.form[formKey.value], `${context}.${dimension}`, '')
}

const setLabelValue = (context: string, dimension: string, value: string | number | null | undefined): void => {
    ensureValue()
    set(props.form[formKey.value], `${context}.${dimension}`, value == null ? '' : String(value))
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            v-for="context in contexts"
            :key="context.key"
            class="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
        >
            <h4 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ context.label }}
            </h4>

            <div class="flex flex-col gap-3">
                <div v-for="dimension in dimensions" :key="`${context.key}-${dimension}`" class="flex flex-col gap-1">
                    <label class="text-xs text-gray-500 dark:text-gray-400">
                        {{ `Rating ${dimension.toUpperCase()}` }}
                    </label>
                    <InputText
                        :model-value="getLabelValue(context.key, String(dimension))"
                        @update:model-value="setLabelValue(context.key, String(dimension), $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
