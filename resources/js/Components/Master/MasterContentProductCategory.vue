<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAlignLeft, faSearch } from "@fas"
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ContentProductCategorySuggestion from './ContentProductCategorySuggestion.vue'
import MasterSeoSuggestion from './MasterSeoSuggestion.vue'

withDefaults(defineProps<{
    data: any
    isMaster?: boolean
}>(), {
    isMaster: false,
})

const layout = inject('layout', layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])
const primaryContrastColor = computed(() => layout.app.theme[5])

const tabs = [
    { key: 'description', label: trans('Description'), icon: faAlignLeft },
    { key: 'seo', label: trans('SEO Content'), icon: faSearch },
]

const currentTab = ref(tabs[0].key)
</script>

<template>
    <div class="flex overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <aside class="flex w-14 shrink-0 flex-col gap-y-1 border-r border-gray-200 bg-gray-50 p-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                @click="currentTab = tab.key"
                class="group relative flex h-10 w-10 items-center justify-center rounded-lg border transition-colors duration-200"
                :class="currentTab === tab.key
                    ? 'shadow-sm'
                    : 'border-transparent text-gray-500 hover:bg-gray-200/70 hover:text-gray-700'
                "
                :style="currentTab === tab.key
                    ? { backgroundColor: primaryColor, borderColor: primaryColor, color: primaryContrastColor }
                    : undefined
                "
            >
                <FontAwesomeIcon :icon="tab.icon" class="text-sm" fixed-width aria-hidden="true" />

                <span
                    class="pointer-events-none absolute left-full top-1/2 z-20 ml-2 hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-gray-800 px-2.5 py-1 text-xs text-white shadow-lg group-hover:block"
                >
                    {{ tab.label }}
                </span>
            </button>
        </aside>

        <div class="min-w-0 flex-1">
            <ContentProductCategorySuggestion
                v-if="currentTab === 'description'"
                :data="data.department"
                :isMaster="isMaster"
            />
           <!--  <MasterSeoSuggestion
                v-else-if="currentTab === 'seo'"
                :data="data"
                :isMaster="isMaster"
            /> -->
        </div>
    </div>
</template>
