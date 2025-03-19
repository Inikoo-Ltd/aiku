<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faThumbtack } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

const props = withDefaults(defineProps<{
    tabName: 'activeUsers' | 'language'
    pinTab?: boolean
    header?: boolean
}>(), {
    header: true
})

library.add(faThumbtack)

const layout = inject('layout', layoutStructure)

const onPinTab = () => {
    layout.rightSidebar[props.tabName].show = !layout.rightSidebar[props.tabName]?.show
    if (typeof window !== "undefined") {
        localStorage.setItem('rightSidebar', JSON.stringify(layout.rightSidebar))
    }
}
</script>

<template>
    <div class="w-40 min-w-min overflow-hidden rounded-t border border-gray-300 border-b-0">
        <slot name="header" />
    
        <!-- Header of Tab Footer (Pin button) -->
        <div v-if="header" class="h-6 flex justify-end items-center pr-1.5"
            :style="{
                background: `linear-gradient(to right, color-mix(in srgb, ${layout?.app?.theme[0]} 80%, black), color-mix(in srgb, ${layout?.app?.theme[0]} 80%, white))`
            }"
        >
            <div v-if="pinTab"
                @click="onPinTab()"
                class="px-1.5 h-full flex items-center leading-none cursor-pointer"
                :class="[layout.rightSidebar[tabName]?.show ? 'text-white' : 'text-white/50  hover:text-white/75' ]"
                v-tooltip="'Pin to right side bar'"
            >
                <FontAwesomeIcon icon="fas fa-thumbtack" class="h-3" title="Pin tab to right side layout" aria-hidden="true" />
            </div>
        </div>

        <!-- The options list -->
        <div class="bg-gray-800 w-full shadow-lg flex-row items-start text-[11px] leading-none"
            :style="{
                //background: `color-mix(in srgb, ${layout?.app?.theme[0]} 10%, white)`,
                // background: '#000',
                color: `#fff`
            }"
        >
            <div class="flex flex-col justify-center text-center pt-0.5 pb-3 gap-y-1">
                <slot />
            </div>
        </div>
    </div>
</template>