<script setup lang="ts">
import { computed, ref } from 'vue'
import { get, set } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import InputText from 'primevue/inputtext'

const props = defineProps<{
    form: any
    fieldName: string | string[]
}>()

const contexts = [
    { key: 'product', label: 'Product Reviews' },
    { key: 'order', label: 'Order Reviews' },
    { key: 'family', label: 'Family Reviews' },
    // { key: 'shop', label: 'Shop Reviews' },
]

const dimensions = ['a', 'b', 'c', 'd', 'e']

const activeContext = ref<string>(contexts[0].key)
const editingLabelTab = ref<string | null>(null)

const formKey = computed(() => {
    return Array.isArray(props.fieldName) ? props.fieldName[0] : props.fieldName
})

const toggleContext = (contextKey: string) => {
    activeContext.value = activeContext.value === contextKey ? '' : contextKey
}

const ensureValue = () => {
    if (!props.form[formKey.value] || typeof props.form[formKey.value] !== 'object') {
        props.form[formKey.value] = {}
    }
}

const getLabelValue = (context: string, dimension: string): string => {
    ensureValue()
    return get(props.form[formKey.value], `${context}.${dimension}`, '')
}

const countDimensions = (context: string): number => {
    ensureValue()
    return dimensions.filter((dimension) => {
        const value = get(props.form[formKey.value], `${context}.${dimension}`)
        return value !== null && value !== undefined && value !== ''
    }).length
}

const setLabelValue = (context: string, dimension: string, value: string | number | null | undefined): void => {
    ensureValue()
    set(props.form[formKey.value], `${context}.${dimension}`, value == null ? '' : String(value))
}

const getLabelTabValue = (context: string): string => {
    ensureValue()
    return get(props.form[formKey.value], `${context}.label_tab`, contexts.find((item) => item.key === context)?.label ?? context)
}

const setLabelTabValue = (context: string, value: string | number | null | undefined): void => {
    ensureValue()
    set(props.form[formKey.value], `${context}.label_tab`, value == null ? '' : String(value))
}

const editLabelTab = (context: string): void => {
    editingLabelTab.value = context
}

const stopEditingLabelTab = (): void => {
    editingLabelTab.value = null
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="context in contexts"
            :key="context.key"
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
        >
            <div class="flex w-full items-center justify-between gap-3 bg-gray-50 px-4 py-3 transition hover:bg-gray-100">
                <button
                    type="button"
                    class="flex-1 text-left"
                    @click="toggleContext(context.key)"
                    :aria-expanded="activeContext === context.key"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-900">
                            <template v-if="editingLabelTab === context.key">
                                <InputText
                                    :id="`${context.key}-label-tab`"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-slate-400 focus:outline-none"
                                    :model-value="getLabelTabValue(context.key)"
                                    @update:model-value="setLabelTabValue(context.key, $event)"
                                    @blur="stopEditingLabelTab"
                                />
                            </template>
                            <template v-else>
                                {{ getLabelTabValue(context.key) }}
                            </template>
                        </span>
                        <span class="flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                            {{ countDimensions(context.key) }} {{ trans('Ratings') }}
                        </span>
                    </div>
                </button>

                <button
                    type="button"
                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                    @click.stop="editLabelTab(context.key)"
                >
                    {{ trans('Edit') }}
                </button>
            </div>

            <div v-show="activeContext === context.key" class="border-t border-gray-200 px-4 py-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="dimension in dimensions"
                        :key="`${context.key}-${dimension}`"
                        class="grid gap-1"
                    >
                        <label :for="`${context.key}-${dimension}`" class="text-[11px] font-medium uppercase tracking-[0.2em] text-slate-500">
                            {{ `Rating ${dimension.toUpperCase()}` }}
                        </label>
                        <InputText
                            :id="`${context.key}-${dimension}`"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 shadow-sm transition focus:border-slate-400 focus:outline-none"
                            :model-value="getLabelValue(context.key, String(dimension))"
                            @update:model-value="setLabelValue(context.key, String(dimension), $event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
