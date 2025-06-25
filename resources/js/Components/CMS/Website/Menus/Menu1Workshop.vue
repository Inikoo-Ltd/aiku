<script setup lang="ts">
import { Collapse } from 'vue-collapsed'
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle } from '@fas';
import { faHeart } from '@far';
import { ref, inject } from 'vue'
import { getStyles } from "@/Composables/styles"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { resolveMigrationLink } from "@/Composables/SetUrl"

library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faUserCircle);

const props = withDefaults(defineProps<{
    fieldValue: {}
    screenType: 'mobile' | 'tablet' | 'desktop'
}>(), {});

const isOpen = ref<number | null>(null)
const layout = inject('layout', layoutStructure)
const migration_redirect = layout?.iris?.migration_redirect

const timeout = ref(null)
const onMouseEnterMenu = (idxNavigation: number) => {
    if(timeout.value) {
        clearTimeout(timeout.value)
    }

    timeout.value = setTimeout(() => {
        isOpen.value = idxNavigation
    }, 300)
}
const onMouseLeaveMenu = () => {
    if(timeout.value) {
        clearTimeout(timeout.value)
        isOpen.value = null
    } else {
        timeout.value = setTimeout(() => {
            isOpen.value = null
        }, 400)
    }
}
</script>

<template>
    <!-- Main Navigation -->
    <div class="bg-white  border-b border-gray-300" :style="getStyles(fieldValue?.container?.properties,screenType)">
        <div class="container  flex flex-col justify-between items-center px-4">

            <!-- Navigation List -->
            <nav class="relative flex text-sm text-gray-600 w-full">
                <div v-for="(navigation, idxNavigation) in fieldValue?.navigation" :key="idxNavigation"
                    @mouseenter="() => (onMouseEnterMenu(idxNavigation))"
                    @mouseleave="() => onMouseLeaveMenu()" :style="getStyles(fieldValue?.navigation_container?.properties,screenType)"
                    class="group w-full hover:bg-gray-100 hover:text-orange-500 p-4 flex items-center justify-center cursor-pointer transition duration-200">
                    <FontAwesomeIcon v-if="navigation.icon" :icon="navigation.icon" class="mr-2" />
                    <div v-if="navigation.type == 'multiple'" class="text-center">
                       <span v-if="!navigation?.link?.href">{{ navigation.label }}</span> 
                        <a v-else :href="resolveMigrationLink(navigation?.link?.href,migration_redirect)" :target="navigation?.link?.target" class="text-center">{{ navigation.label }}</a>
                    </div>
                    
                    <a v-else :href="resolveMigrationLink(navigation?.link?.href,migration_redirect)" :target="navigation?.link?.target" class="text-center">{{ navigation.label }}</a>
                    
                    <FontAwesomeIcon v-if="navigation.type == 'multiple'" :icon="faChevronDown"
                        class="ml-2 text-[11px]" fixed-width />

                    <!-- Sub-navigation -->
                    <Collapse
                        v-if="navigation.subnavs"
                        :when="isOpen === idxNavigation"
                        as="div"
                        class="absolute left-0 top-full bg-white border border-gray-300 w-full shadow-lg"
                        :style="getStyles(fieldValue?.container?.properties,screenType)"
                        :class="isOpen === idxNavigation ? 'z-50' : 'z-0'"
                        
                    >
                        <div class="grid grid-cols-4 gap-3 p-6">
                            <div v-for="subnav in navigation.subnavs" :key="subnav.title" class="space-y-4">
                                <div v-if="!subnav?.link?.href && subnav.title"  :style="getStyles(fieldValue?.sub_navigation?.properties,screenType)" class="font-semibold text-gray-700">{{ subnav.title }}</div>
                                <!-- Sub-navigation Links -->
                                <div class="flex flex-col gap-y-3">
                                    <div v-for="link in subnav.links" :key="link.url" class="flex items-center gap-x-3">
                                        <FontAwesomeIcon :icon="link.icon || faChevronRight"
                                            class="text-[10px] text-gray-400" />
                                        <a :href="resolveMigrationLink(link?.link?.href,migration_redirect)" :target="link?.link?.target" :style="getStyles(fieldValue?.sub_navigation_link?.properties,screenType)" 
                                            class="text-gray-500 hover:text-orange-500 hover:underline transition duration-200">
                                            {{ link.label }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Collapse>
                </div>
            </nav>
        </div>
    </div>
</template>

<style scoped>
.container {
    max-width: 1980px;
}
</style>
