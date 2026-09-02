<script setup lang="ts">
import { computed, inject, toRef } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { useMasterShopsContent } from '@/Composables/useMasterShopsContent'
import ShopContentTabs from './ShopContentTabs.vue'

type ComparableField = 'name' | 'description' | 'description_extra'

const props = withDefaults(defineProps<{
    data: {
        id?: number
        name?: string
        title?: string
        description?: string
        description_extra?: string
    }
    isMaster?: boolean
}>(), {
    isMaster: false,
})

const layout = inject('layout', layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])

const { shops, isLoading, error, selectedShopId, selectedShop, fetchShops } = useMasterShopsContent(
    toRef(() => props.data?.id)
)

const fields: { key: ComparableField, label: string, isHtml: boolean }[] = [
    { key: 'name', label: trans('Name'), isHtml: false },
    { key: 'description', label: trans('Description'), isHtml: true },
    { key: 'description_extra', label: trans('Description extra'), isHtml: true },
]

const masterValue = (field: ComparableField) => props.data?.[field] || ''
const shopValue = (field: ComparableField) => selectedShop.value?.[field] || ''

const isFieldDifferent = (field: ComparableField) => {
    if (!selectedShop.value) {
        return false
    }

    return masterValue(field) !== shopValue(field)
}
</script>

<template>
    <div class="p-6">
        <ShopContentTabs
            class="mb-4"
            :shops="shops"
            :isLoading="isLoading"
            :error="error"
            :modelValue="selectedShopId"
            @update:modelValue="selectedShopId = $event"
            @retry="fetchShops"
        />

        <div class="grid grid-cols-1 gap-x-8 md:grid-cols-2">
            <div class="mb-2 hidden rounded-lg px-3 py-2 md:block" :style="{ backgroundColor: `${primaryColor}14` }">
                <span class="text-sm font-semibold" :style="{ color: primaryColor }">{{ trans('Master') }}</span>
            </div>

            <div class="mb-2 hidden items-center justify-between gap-2 rounded-lg bg-sky-50 px-3 py-2 md:flex">
                <span class="truncate text-sm font-semibold text-sky-700">
                    {{ selectedShop?.shop_name || trans('No shop uses this master yet') }}
                </span>
                <span
                    v-if="selectedShop?.follow_master"
                    class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700"
                >
                    {{ trans('Follow master') }}
                </span>
            </div>

            <template v-for="field in fields" :key="field.key">
                <div class="flex items-center gap-2 border-t border-gray-200 pb-1 pt-3 md:col-span-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ field.label }}</h3>
                </div>

                <div class="mb-3 rounded-lg border-l-2 px-3 py-2" :style="{ borderColor: primaryColor, backgroundColor: `${primaryColor}0d` }">
                    <div class="mb-0.5 text-[10px] uppercase tracking-wide md:hidden" :style="{ color: primaryColor }">
                        {{ trans('Master') }}
                    </div>
                    <p v-if="!masterValue(field.key)" class="text-sm text-gray-400">—</p>
                    <p
                        v-else-if="field.isHtml"
                        class="whitespace-pre-line text-sm text-gray-600"
                        v-html="masterValue(field.key)"
                    />
                    <p v-else class="whitespace-pre-line text-sm text-gray-600">{{ masterValue(field.key) }}</p>
                </div>

                <div class="mb-3 rounded-lg border-l-2 border-sky-400 bg-sky-50/60 px-3 py-2">
                    <div class="mb-0.5 text-[10px] uppercase tracking-wide text-sky-600 md:hidden">
                        {{ selectedShop?.shop_code || trans('Shop') }}
                    </div>
                    <p v-if="!shopValue(field.key)" class="text-sm text-gray-400">—</p>
                    <p
                        v-else-if="field.isHtml"
                        class="whitespace-pre-line text-sm text-gray-600"
                        v-html="shopValue(field.key)"
                    />
                    <p v-else class="whitespace-pre-line text-sm text-gray-600">{{ shopValue(field.key) }}</p>
                </div>
            </template>
        </div>
    </div>
</template>
