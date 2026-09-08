<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faChevronRight } from "@fal"

library.add(faChevronDown, faChevronRight)

const props = defineProps<{
    storageKey: string
}>()

const collapsed = ref(localStorage.getItem(props.storageKey) === "1")
const toggle = () => {
    collapsed.value = !collapsed.value
    localStorage.setItem(props.storageKey, collapsed.value ? "1" : "0")
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="toggle">
                <FontAwesomeIcon :icon="collapsed ? 'fal fa-chevron-right' : 'fal fa-chevron-down'" fixed-width aria-hidden="true" />
            </button>
            <slot name="header" :collapsed="collapsed" :toggle="toggle" />
        </div>
        <div v-show="!collapsed" class="border-t border-gray-100 px-4 pb-4 pt-3">
            <slot />
        </div>
    </div>
</template>
