<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Feb 2024 08:27:43 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang='ts'>
import { Navigation } from "@/types/Navigation";
import { Link } from "@inertiajs/vue3";
import { isNavigationActive } from "@/Composables/useUrl";
import { computed, inject, ref } from "vue";
import RetinaNavigationSimple from "@/Layouts/Retina/RetinaNavigationSimple.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faChevronLeft, faChevronRight } from "@fas";
import { faMoneyBillWave, faParachuteBox } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure";
import { routeType } from "@/types/route"
import { useTruncate } from "@/Composables/useTruncate"
import { trans } from "laravel-vue-i18n"
import Fieldset from "primevue/fieldset"

library.add(faChevronLeft, faChevronRight, faParachuteBox, faMoneyBillWave)

const props = defineProps<{
    nav: {
        field_name?: string
        field_icon?: string
        before_horizontal: {
            subNavigation: Navigation[]
        }
        horizontal_navigations: {
            label: string
            icon: string
            key: string
            root: string
            route: routeType
            subNavigation: Navigation[]
        }[]
    }

}>()

const layout = inject('layout', retinaLayoutStructure)


const currentActiveHorizontal = computed(() => {
    const xxx =  props.nav.horizontal_navigations?.find(horizontal => {
        return horizontal.key == layout.currentPlatform
    })

    if (!xxx) {
        return props.nav.horizontal_navigations[0]
    }

    return xxx
})
const currentIndexHorizontal = computed(() => {
    return props.nav.horizontal_navigations.findIndex(nav => nav.key == layout.currentPlatform)
})

const isSomeSubnavActive = () => {
    return Object.values(currentActiveHorizontal.value || {}).some(nav => (isNavigationActive(layout.currentRoute, currentActiveHorizontal.value?.root)))
}


// Section: Route for arrow chevron
const previousHorizontal = computed(() => {
    return props.nav.horizontal_navigations?.[currentIndexHorizontal.value - 1] || undefined
})
const nextHorizontal = computed(() => {
    return props.nav.horizontal_navigations?.[currentIndexHorizontal.value + 1] || undefined
})


const isLoadingNavigation = ref<string | boolean>(false)
const storageLayout = JSON.parse(localStorage.getItem(`layout_${layout.retina.type}`) || '{}')  // Get layout from localStorage
const onClickArrow = (horizontalKey: string) => {
    // layout.currentPlatform = horizontalKey
    // localStorage.setItem(`layout_${layout.retina.type}`, JSON.stringify({
    //     ...storageLayout,
    //     currentPlatform: horizontalKey
    // }))
    isLoadingNavigation.value = false
}

const isLoadingVisitActiveHorizontal = ref(false)
</script>

<template>
    <Fieldset class="relative isolate ring-1 ring-white/20 rounded transition-all "
        unstyled
        :class="layout.leftSidebar.show ? 'px-1' : 'px-0'"
        :style="{ 'box-shadow': `0 0 0 1px ${layout.app.theme[1]}55` }">
        <template v-if="nav.field_name" #legend>
            <div class="ml-2 px-2 rounded mb-0.5 text-sm" :style="{ 'background-color': `${layout.app.theme[0]}` }">
                {{ nav.field_name }}
                <FontAwesomeIcon :icon="nav.field_icon" class="opacity-70" fixed-width aria-hidden="true" />
            </div>
        </template>

        <!-- Section: Before horizontal -->
        <div v-if="nav.before_horizontal?.subNavigation" class="py-1 border-b border-gray-600 space-y-1.5">
            <template v-for="nav, navIndex in nav.before_horizontal?.subNavigation" :key="`${navIndex}before_horizontal`">
                <RetinaNavigationSimple :nav="nav" :navKey="navIndex" />
            </template>
        </div>

        <div v-if="!!currentActiveHorizontal" class="relative w-full flex justify-between items-center pt-2 pl-2 pr-0.5 pb-2"
            :style="{ color: layout.app.theme[1] + '99' }">

            <!-- Section: Horizontal label -->
            <component
                :is="currentActiveHorizontal.route.name ? Link : 'div'"
                :href="route(currentActiveHorizontal.route.name, currentActiveHorizontal.route.parameters)"
                @start="() => isLoadingVisitActiveHorizontal = true"
                @finish="() => isLoadingVisitActiveHorizontal = false"
                class="relative flex gap-x-1.5 items-center pt-1 select-none"
                :class="currentActiveHorizontal.route.name ? 'cursor-pointer hover:text-gray-200' : 'cursor-default'"
            >
                <Transition name="spin-to-right">
                    <FontAwesomeIcon v-if="currentActiveHorizontal?.icon" :key="currentActiveHorizontal?.icon" :icon="currentActiveHorizontal?.icon" class='text-xs' fixed-width aria-hidden='true' v-tooltip="currentActiveHorizontal?.label" />
                </Transition>

                <Transition v-if="currentActiveHorizontal?.img" name="spin-to-down">
                    <div :key="currentActiveHorizontal?.img" class="h-4 w-[18.5px] flex items-center justify-center">
                        <LoadingIcon v-if="isLoadingVisitActiveHorizontal" class=""/>
                        <img v-else :src="currentActiveHorizontal?.img" :alt="trans('Logo')" class="h-4 w-[17.5px]" />
                    </div>
                </Transition>

                <Transition name="slide-to-left">
                    <div v-if="layout.leftSidebar.show" class="flex items-end gap-x-0.5 w-32">
                        <Transition name="spin-to-down">
                            <span :key="currentActiveHorizontal?.label" class="whitespace-nowrap text-base leading-[10px]">
                                {{ useTruncate(currentActiveHorizontal?.label, 14) }}
                            </span>
                        </Transition>
                    </div>
                </Transition>
            </component>

            <!-- Section: Horizontal arrow left-right -->
            <Transition v-if="previousHorizontal || nextHorizontal" name="slide-to-left">
                <div v-if="layout.leftSidebar.show" class="absolute right-0.5 top-3.5 flex text-white text-xxs" >
                    <component
                        :key="previousHorizontal?.key"
                        :is="previousHorizontal?.route?.name ? Link : 'div'"
                        v-tooltip=""
                        :href="previousHorizontal?.route?.name ? route(previousHorizontal.route.name, previousHorizontal.route.parameters) : '#'"
                        class="py-0.5 px-[1px] flex justify-center items-center rounded"
                        :class="previousHorizontal ? 'hover:bg-black/10' : 'text-white/40'"
                        @start="() => isLoadingNavigation = 'prevNav'"
                        @finish="() => onClickArrow(previousHorizontal?.key)"
                    >
                        <LoadingIcon v-if="isLoadingNavigation == 'prevNav'" />
                        <FontAwesomeIcon v-else icon='fas fa-chevron-left' class='' fixed-width aria-hidden='true' />
                    </component>

                    <component
                        :key="nextHorizontal?.key"
                        :is="nextHorizontal?.route?.name ? Link : 'div'"
                        :href="nextHorizontal?.route?.name ? route(nextHorizontal.route.name, nextHorizontal.route.parameters) : '#'"
                        class="py-0.5 px-[1px] flex justify-center items-center rounded"
                        :class="nextHorizontal ? 'hover:bg-black/10' : 'text-white/40'"
                        @start="() => isLoadingNavigation = 'nextNav'"
                        @finish="() => onClickArrow(nextHorizontal?.key)"
                    >
                        <LoadingIcon v-if="isLoadingNavigation == 'nextNav'" />
                        <FontAwesomeIcon v-else icon='fas fa-chevron-right' class='' fixed-width aria-hidden='true' />
                    </component>
                </div>
            </Transition>
        </div>

        <!-- Section: Sub Navigaiton -->
        <div class="flex flex-col gap-y-1 mb-1">
            <template v-for="nav, navIndex in currentActiveHorizontal?.subNavigation" :key="`${navIndex}`">
                <RetinaNavigationSimple :nav="nav" :navKey="navIndex" />
            </template>
        </div>
        <!-- curplat: {{ layout.currentPlatform }} <br>
        index: {{ currentActiveHorizontal.key }} <br>
        prev: {{ previousHorizontal?.key }}<br>
        next: {{ nextHorizontal?.key }} <br>

        <pre>{{ props.nav.horizontal_navigations.map(x => x.key).findIndex(x => x == currentActiveHorizontal.key) }}</pre> -->
        <div v-if="isSomeSubnavActive()" class="absolute inset-0 bg-slate-50/10 rounded -z-10" />
    </Fieldset>
</template>
