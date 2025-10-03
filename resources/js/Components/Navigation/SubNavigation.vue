<script setup lang="ts">
import { routeType } from "@/types/route"
import { Link } from "@inertiajs/vue3"
import { inject, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faAtomAlt, faDotCircle, faUserFriends, faChessBoard,faStopwatch,faClipboardListCheck } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faAtomAlt, faDotCircle, faUserFriends, faChessBoard, faStopwatch, faClipboardListCheck);

const layout = inject("layout", layoutStructure)

interface SubNav {
    isAnchor?: boolean
    leftIcon: {
        icon: string | string[]
        tooltip: string
    }
    align: "left" | "right"
    root?: string
    route: routeType
    label: string
    number: string
}

const props = defineProps<{
    dataNavigation: SubNav[]
}>()

const isLoading = ref<string | boolean | number>(false)
const locale = inject("locale", aikuLocaleStructure)

const isSubNavActive = (subNav: SubNav) => {
    const isRouteIncludeRoot = layout.currentRoute.includes(subNav.root || 'xxxxxxxxxxxxxxxxxxxxxxxxxxx')
    const isRouteSameAsRoot = layout.currentRoute === subNav.route?.name
    const isLayoutRootActiveExist = subNav.route?.name?.includes(layout.root_active || 'xxxxxxxxxxxxxxxxxxxxxxxxxxx') 

    return  isRouteIncludeRoot || isRouteSameAsRoot || isLayoutRootActiveExist
}

// const originUrl = location.origin
</script>

<template>
    <div
        class="relative select-none w-full flex pr-4 sm:mt-1 lg:mt-0 border-b border-gray-300 sm:gap-y-1 items-end text-gray-400 text-xs">
        <!-- Tab: Home/dashboard -->
        <!-- <div v-if="dataNavigation.length && false"
    class="py-1 flex items-center transition-all"
    :class="[
        layout.currentRoute === dataNavigation[0]?.route?.name ? 'text-indigo-500 px-2 bg-white rounded-t-md rounded-tl-none sm:border sm:border-transparent sm:border-r-gray-300' : 'tabSubNav -ml-2 md:ml-0'
    ]"
>
    <component :is="dataNavigation[0].route?.name ? Link : 'div'"
        class="flex items-center py-1.5 px-3 rounded transition-all"
        :href="dataNavigation[0].route.name ? route(dataNavigation[0].route.name, dataNavigation[0].route.parameters) : '#'"
        @start="() => isLoading = 'home'"
        @finish="() => isLoading = false"
        :class="[
            layout.currentRoute === dataNavigation[0].route.name ? `` : `bg-gray-100 hover:bg-gray-200 text-gray-600`
        ]"
        :style="{
            backgroundColor: layout.currentRoute === dataNavigation[0].route.name ? layout?.app?.theme[4] + '22' : '',
            color: layout.currentRoute === dataNavigation[0].route.name ? `color-mix(in srgb, ${layout?.app?.theme[4]} 50%, black)` : ''
        }"
    >
        <div v-if="dataNavigation[0].leftIcon" class="pr-1">
            <FontAwesomeIcon v-if="isLoading === 'home'" icon="fad fa-spinner-third" fixed-width aria-hidden="true" class="animate-spin" />
            <FontAwesomeIcon v-else :icon="dataNavigation[0].leftIcon.icon" fixed-width aria-hidden="true" class="" />
        </div>

        <div class="xl:whitespace-nowrap">
            <span class="leading-none">{{ useTruncate(dataNavigation[0].label, 16) }}</span>

            <span v-if="dataNavigation[0].number">
                <template v-if="typeof dataNavigation[0].number == 'number'">
                    <template v-if="dataNavigation[0].number > 0">
                        ({{ locale.number(dataNavigation[0].number) }})
                    </template>
                    <template v-else>
                        <FontAwesomeIcon icon='fal fa-empty-set' class='' fixed-width aria-hidden='true' />
                    </template>
                </template>
                <template v-else>
                    ({{ dataNavigation[0].number }})
                </template>
            </span>
        </div>
    </component>
</div> -->

        <!-- Tabs: Left -->
        <div class="w-full flex">
            <TransitionGroup>
                <template v-for="subNav, itemIdx in dataNavigation" :key="'subNav' + itemIdx">
                    <component v-if="subNav.align !== 'right'" :is="subNav.route?.name ? Link : 'div'"
                        :href="subNav.route?.name ? route(subNav.route.name, subNav.route.parameters) : '#'"
                        @start="() => isLoading = itemIdx" @finish="() => isLoading = false"
                        class="group pt-2 pb-1.5 px-3 flex w-fit items-center gap-x-2" :class="[
                            isSubNavActive(subNav) ? subNav.isAnchor ? 'anchorSubnavActive mr-3' : 'tabSubNavActive' : subNav.isAnchor ? 'anchorSubnavNotActive mr-3' : 'tabSubNav',
                        ]">
                        <div class="flex items-center">
                            <FontAwesomeIcon v-if="isLoading === itemIdx" icon="fad fa-spinner-third"
                                v-tooltip="subNav.leftIcon?.tooltip" fixed-width aria-hidden="true"
                                class="text-base animate-spin" />
                            <FontAwesomeIcon v-else-if="subNav.leftIcon" :icon="subNav.leftIcon?.icon"
                                v-tooltip="subNav.leftIcon?.tooltip"
                                class="text-base group-hover:opacity-100 opacity-50" fixed-width aria-hidden="true" />
                        </div>

                        <div class="xl:whitespace-nowrap flex items-center gap-x-1.5">
                            <span class="leading-none text-sm xl:text-base">{{ subNav.label }}</span>
                            <div v-if="typeof subNav.number == 'number'"
                                class="inline-flex items-center w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                :class="layout.currentRoute.includes(subNav.root || 'xxxxxxxxxxxxxxxxxxxxxxxxxxx') || layout.currentRoute === subNav.route?.name ? 'bg-indigo-100 ' : 'bg-gray-200 '">
                                {{ locale.number(subNav.number || 0) }}
                            </div>
                        </div>
                    </component>
                </template>
            </TransitionGroup>
        </div>

        <!-- Tabs: Right -->
        <div class="flex">
            <TransitionGroup>
                <template v-for="subNav, itemIdx in dataNavigation" :key="'subNav' + itemIdx">
                    <component v-if="subNav.align === 'right'" :is="subNav.route?.name ? Link : 'div'"
                        :href="subNav.route?.name ? route(subNav.route.name, subNav.route.parameters) : '#'"
                        @start="() => isLoading = itemIdx" @finish="() => isLoading = false"
                        class="group py-1.5 px-3 flex items-center gap-x-2 transition-all" :class="[
                            layout.currentRoute.includes(subNav.root || 'xxxxxxxxxxxxxxxxxxxxxxxxxxx') || layout.currentRoute === subNav.route?.name ? 'tabSubNavActive' : 'tabSubNav',
                        ]">

                        <div class="flex items-center">
                            <FontAwesomeIcon v-if="isLoading === itemIdx" icon="fad fa-spinner-third"
                                v-tooltip="subNav.leftIcon?.tooltip" fixed-width aria-hidden="true"
                                class="text-sm animate-spin" />
                            <FontAwesomeIcon v-else-if="subNav.leftIcon" :icon="subNav.leftIcon?.icon"
                                v-tooltip="subNav.leftIcon?.tooltip"
                                class="text-sm group-hover:opacity-100 opacity-50" fixed-width aria-hidden="true" />
                        </div>

                        <div class="xl:whitespace-nowrap flex items-center gap-x-1.5">
                            <span class="leading-none text-sm xl:text-base">{{ subNav.label }}</span>
                            <div v-if="typeof subNav.number == 'number'"
                                class="inline-flex items-center w-fit rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
                                :class="layout.currentRoute.includes(subNav.root || 'xxxxxxxxxxxxxxxxxxxxxxxxxxx') || layout.currentRoute === subNav.route?.name ? 'bg-indigo-100 ' : 'bg-gray-200 '">
                                {{ locale.number(subNav.number || 0) }}
                            </div>
                        </div>
                    </component>

                </template>
            </TransitionGroup>
        </div>

        <!-- <div class="hidden border-b border-gray-300 px-1 sm:flex flex-auto">&nbsp</div> -->

    </div>
</template>

<style lang="scss" scoped>
// .tabSubNavActive {
//     @apply px-2 bg-white border sm:border-b-transparent rounded-md sm:rounded-b-none sm:rounded-t-md border-gray-300;

//     color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 40%, black)`') !important;
// }

// .tabSubNav {
//     @apply px-2 sm:border border-transparent border-b-gray-300;

//     color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`') !important;

//     &:hover {
//         color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 60%, black)`') !important;
//     }
// }

.tabSubNavActive {
    border-bottom: v-bind('`1px solid ${layout.app.theme[0]}`');
    color: v-bind('`${layout?.app?.theme[0]}`') !important;
}

.tabSubNav {
    @apply text-gray-500 border-b border-transparent;
}


.anchorSubnavActive {
    background: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 15%, transparent)`') !important;
    color: v-bind('`${layout?.app?.theme[0]}`') !important;
    outline: none !important;
    position: relative;
    text-decoration: none;
}

.anchorSubnavActive:focus:after,
.anchorSubnavActive:focus,
.anchorSubnavActive:hover {
    background: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 30%, transparent)`') !important;
}

.anchorSubnavActive:hover:after {
    background: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 18%, transparent)`') !important;
}

.anchorSubnavActive:after,
.anchorSubnavActive:before {
    // background: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 15%, transparent)`') !important;
    bottom: 0;
    clip-path: polygon(50% 50%, -50% -50%, 0 100%);
    content: "";
    left: 100%;
    position: absolute;
    top: 0;
    width: 20px;
    z-index: 1;
}

.anchorSubnavActive:before {
    background: v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 15%, transparent)`') !important;
}

.anchorSubnavNotActive {
    @apply text-gray-500 bg-gray-200;

    outline: none;
    position: relative;
    text-decoration: none;
}


.anchorSubnavNotActive:focus:after,
.anchorSubnavNotActive:focus,
.anchorSubnavNotActive:hover:after,
.anchorSubnavNotActive:hover {
    background: #e1e1e1 !important;
}

.anchorSubnavNotActive:after,
.anchorSubnavNotActive:before {
    @apply bg-gray-200;

    bottom: 0;
    clip-path: polygon(50% 50%, -50% -50%, 0 100%);
    content: "";
    left: 99.3%;
    position: absolute;
    top: 0;
    width: 20px;
    z-index: 1;
}

.anchorSubnavNotActive:before {
    @apply bg-gray-300;
}

</style>