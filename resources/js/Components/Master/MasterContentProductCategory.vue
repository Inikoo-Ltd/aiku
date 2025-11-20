<script setup lang="ts">
import { ref, inject } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAlignLeft, faSearch } from "@fas"
import ContentProductCategorySuggestion from './ContentProductCategorySuggestion.vue';
import MasterSeoSuggestion from './MasterSeoSuggestion.vue';

const props = withDefaults(defineProps<{
    data: any
    isMaster?: boolean
}>(), {
    isMaster: false,
})

const layout: any = inject("layout", {})

const tabs = [
    { key: "description", label: "Description", icon: faAlignLeft },
    { key: "seo", label: "SEO Content", icon: faSearch },
]

const current = ref(0)
</script>

<template>
    <div class="flex bg-white border overflow-hidden shadow-sm">

        <!-- Sidebar Compact -->
        <aside class="flex flex-col w-14 py-4 border-r bg-gray-50/80 backdrop-blur-sm">
            <button
                v-for="(tab, index) in tabs"
                :key="tab.key"
                @click="current = index"
                class="relative group flex items-center justify-center mx-2 mb-2 p-3 rounded-xl transition-all"
                :class="current === index
                    ? 'buttonPrimary shadow-sm'
                    : 'text-gray-600 hover:bg-gray-100'
                "
            >
                <FontAwesomeIcon :icon="tab.icon" class="w-4 h-4" />

                <!-- Tooltip -->
                <span
                    class="absolute top-1/2 -translate-y-1/2 left-full ml-2
                    hidden group-hover:flex pointer-events-none
                    bg-gray-800 text-white text-xs px-3 py-1 rounded-lg shadow-lg
                    whitespace-nowrap z-20"
                >
                    {{ tab.label }}
                </span>
            </button>
        </aside>

        <!-- Content -->
        <div class="flex-1">
            <div v-if="current === 0">
                <ContentProductCategorySuggestion  :data="data.department "/>
            </div>

            <div v-if="current === 1">
                <MasterSeoSuggestion />
            </div>
        </div>
    </div>
</template>


<style lang="scss">
.buttonPrimary {
  background-color: v-bind('layout?.app?.theme[4]') !important;
  color: v-bind('layout?.app?.theme[5]') !important;
  border: v-bind('`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`');
  transition: all .2s ease;

  &:hover {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, black)`') !important;
    transform: translateY(-1px);
  }

  &:disabled {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 50%, grey)`') !important;
  }
}

</style>
