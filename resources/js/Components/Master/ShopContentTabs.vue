<script setup lang="ts">
import { computed, inject } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationTriangle } from '@fal'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import type { ShopContent } from '@/Composables/useMasterShopsContent'

const props = defineProps<{
    shops: ShopContent[]
    isLoading: boolean
    error: string | null
    modelValue: number | null
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: number): void
    (e: 'retry'): void
}>()

const layout = inject('layout', layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])
const primaryContrastColor = computed(() => layout.app.theme[5])
</script>

<template>
    <div v-if="isLoading" class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-400">
        <LoadingIcon />
        {{ trans('Loading shops content') }}
    </div>

    <div v-else-if="error" class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600">
        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
        {{ error }}
        <button type="button" class="text-xs text-gray-500 underline hover:text-gray-700" @click="emits('retry')">
            {{ trans('Retry') }}
        </button>
    </div>

    <div v-else-if="!shops.length" class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-400">
        {{ trans('No shop uses this master yet') }}
    </div>

    <div v-else class="tinyScrollbar flex gap-x-1.5 overflow-x-auto pb-1">
        <button
            v-for="shop in shops"
            :key="shop.id"
            type="button"
            v-tooltip="shop.shop_name"
            @click="emits('update:modelValue', shop.id)"
            class="shrink-0 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-semibold transition-all duration-200"
            :class="modelValue === shop.id
                ? 'shadow-sm'
                : 'border-gray-200 bg-gray-50 text-gray-500 hover:border-gray-300 hover:bg-gray-100 hover:text-gray-700'
            "
            :style="modelValue === shop.id
                ? { backgroundColor: primaryColor, borderColor: primaryColor, color: primaryContrastColor }
                : undefined
            "
        >
            {{ shop.shop_code }}
        </button>
    </div>
</template>

<style scoped>
.tinyScrollbar {
    scrollbar-width: thin;
    scrollbar-color: theme('colors.gray.300') transparent;
}

.tinyScrollbar::-webkit-scrollbar {
    height: 6px;
}

.tinyScrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.tinyScrollbar::-webkit-scrollbar-thumb {
    background-color: theme('colors.gray.300');
    border-radius: 9999px;
}
</style>
