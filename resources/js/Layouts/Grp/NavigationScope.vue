<script setup lang='ts'>
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import NavigationSimple from '@/Layouts/Grp/NavigationSimple.vue'
import { Navigation } from '@/types/Navigation'

const props = defineProps<{
    navs: {
        [key: string]: Navigation
    }
    icon: string
    scope: string
    root: string
}>()

const layout = inject('layout', layoutStructure)

</script>

<template>
    <div class="flex flex-col relative isolate ring-1 ring-white/20 rounded-sm"
        :class="layout.leftSidebar.show ? 'px-1' : 'px-0'"
        :style="{ 'box-shadow': `0 0 0 1px ${layout.app.theme[1]}55` }">
        <div class="w-full flex items-center pt-2 pl-2.5 pr-0.5 pb-2 gap-x-1.5"
            :style="{ color: layout.app.theme[1] + '99' }">
            <FontAwesomeIcon :icon="icon" class='text-xxs' fixed-width aria-hidden='true' />
            <Transition name="slide-to-left">
                <div v-if="layout.leftSidebar.show" class="flex items-center gap-x-1.5">
                    <!-- <span class="text-sm leading-none uppercase">
                        xxx
                    </span> -->
                    <span class="text-xs leading-none">
                        {{ scope }}
                    </span>
                </div>
            </Transition>
        </div>

        <div v-for="nav, navIndex in navs" :key="scope + navIndex" class="flex flex-col gap-y-1 mb-1">
            <NavigationSimple :nav="nav" :navKey="scope + 's'" />
        </div>

        <Transition name="slide-to-right">
            <div v-if="layout.currentRoute.includes(root)" class="absolute inset-0 bg-black/20 rounded-sm -z-10" />
        </Transition>
    </div>
</template>