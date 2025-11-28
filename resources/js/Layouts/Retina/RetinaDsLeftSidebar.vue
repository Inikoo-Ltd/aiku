<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 Mar 2023 13:49:56 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import RetinaLeftSidebarNavigation from "@/Layouts/Retina/RetinaLeftSidebarNavigation.vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faCopy } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import SwitchLanguage from "@/Components/Iris/SwitchLanguage.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { useCopyText } from "@/Composables/useCopyText"
import { set } from "lodash-es"
library.add(faChevronLeft, faCopy)

const layout = useLayoutStore()

// Set the LeftSidebar value to local storage
const handleToggleLeftBar = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem('leftSideBar', (!layout.leftSidebar.show).toString())
    }
    layout.leftSidebar.show = !layout.leftSidebar.show
}
</script>

<template>
    <div
        :style="{
            'background-color': layout.app.theme[0] + '00',
            'color': layout.app.theme[1]
        }"
        id="leftSidebar"
    >
        <!-- Reference -->
        <div class="hidden md:block absolute bottom-full left-3" :class="layout.leftSidebar.show ? '' : 'px-2' " v-tooltip="layout.leftSidebar.show ? '' : `Reference: #${layout?.iris?.customer?.reference}`">
            <div v-if="layout.leftSidebar.show" class="text-xxs text-gray-500 -mb-1 italic">
                {{ trans("Customer reference:") }}
            </div>
            <div class=" text-xl text-[#1d252e] font-semibold flex items-center gap-2">
                <Transition name="slide-to-left">
                    <span v-if="layout.leftSidebar.show">#{{layout?.iris?.customer?.reference ?? '-'}}</span>
                </Transition>
                <FontAwesomeIcon 
                    v-if="layout.leftSidebar.show && layout?.iris?.customer?.reference" 
                    @click="useCopyText(layout?.iris?.customer?.reference)" 
                    icon="far fa-copy"
                    class="text-sm cursor-pointer opacity-50 hover:opacity-100 transition-opacity"
                    v-tooltip="trans('Copy reference to clipboard')"
                />
            </div>
        </div>

        <div class="shadow pt-4 md:pt-0 rounded-md flex flex-grow flex-col h-full overflow-y-auto custom-hide-scrollbar pb-4"
            :style="{
                'background-color': layout.app.theme[0],
                'color': layout.app.theme[1]
            }"
        >
            <!-- Switch Language -->
            <div v-if="layout.app.environment !== 'production' && Object.values(layout.iris.website_i18n?.language_options || {})?.length" class="md:hidden bg-gray-100/50 text-white px-4 mb-3 flex justify-between items-center text-xs">
                <div>{{ trans("Language") }}:</div>
                <SwitchLanguage>
                    <template #default="{ isLoadingChangeLanguage }">
                        <div class="underline text-xs py-2">
                            {{ Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.website_i18n.current_language?.code)?.name }}
                            <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" :alt="layout.iris.website_i18n.current_language?.code" title='capitalize(countryName)'  />
                        </div>
                    </template>
                </SwitchLanguage>
            </div>

            <div class="md:hidden bottom-full left-3 px-4 border-b border-gray-300/30 pb-1">
                <div class="text-xxs opacity-50 -mb-1 italic">
                    {{ trans("Customer reference:") }}
                </div>
                <div class=" text-xl font-semibold flex items-center gap-2">
                    <span>#{{layout?.iris?.customer?.reference ?? '-'}}</span>
                    <FontAwesomeIcon 
                        v-if="layout?.iris?.customer?.reference" 
                        @click="useCopyText(layout?.iris?.customer?.reference)" 
                        icon="far fa-copy"
                        class="text-sm cursor-pointer opacity-50 hover:opacity-100 transition-opacity"
                        v-tooltip="trans('Copy reference to clipboard')"
                    />
                </div>
            </div>

            <div @click="handleToggleLeftBar"
                class="hidden absolute z-10 right-1/2 bottom-0 xtop-2/4 translate-y-1/2 translate-x-1/2 w-6 aspect-square border border-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
                :title="layout.leftSidebar.show ? 'Collapse the bar' : 'Expand the bar'"
                :style="{
                    'background-color':  `color-mix(in srgb, ${layout.app.theme[0]} 85%, black)`,
                    'color': layout.app.theme[1]
                }"
            >
                <div class="flex items-center justify-center transition-all duration-300 ease-in-out"
                    :class="{'rotate-180': !layout.leftSidebar.show}"
                >
                    <FontAwesomeIcon icon='far fa-chevron-left' class='h-[10px] leading-none' aria-hidden='true'
                        :class="layout.leftSidebar.show ? '-translate-x-[1px]' : ''"
                    />
                </div>
            </div>
            
            <RetinaLeftSidebarNavigation>
                <template #default>
                    <a
                        v-if="layout.retina.portal_link"
                        :href="layout.retina.portal_link"
                        class="relative group hover:underline rounded-md py-2 w-full group flex items-center text-sm gap-x-2" xclass="[open ? 'bg-black/25' : '']"
                        :class="
                            layout.leftSidebar.show ? 'px-2' : 'px-3'
                        "
                        v-tooltip="{ content: trans('Open help portal'), delay: { show: layout.leftSidebar.show ? 500 : 100, hide: 100 } }"
                        :style="{
                            color: layout?.app?.theme[1],
                        }"
                        target="_blank"
                    >
                        <FontAwesomeIcon aria-hidden="true" class="flex-shrink-0 h-4 w-4" fixed-width icon="fal fa-life-ring" />
                        
                        <Transition name="slide-to-left">
                            <span v-if="layout.leftSidebar.show" class="py-0.5 leading-none whitespace-nowrap "
                                :class="[layout.leftSidebar.show ? 'truncate block md:block' : 'block md:hidden']">
                                {{ trans('Help') }}
                            </span>
                        </Transition>

                        <FontAwesomeIcon v-if="layout.leftSidebar.show" icon="fal fa-external-link-alt" class="opacity-50 group-hover:opacity-100 absolute right-4 text-[var(--theme-color-1)]" fixed-width aria-hidden="true" />
                    </a>
                </template>
            </RetinaLeftSidebarNavigation>
            
        </div>


    </div>
</template>

<style>
/* Hide scrollbar for Chrome, Safari and Opera */
.custom-hide-scrollbar::-webkit-scrollbar {
    display: none;
}

/* Hide scrollbar for IE, Edge and Firefox */
.custom-hide-scrollbar {
    -ms-overflow-style: none;
    /* IE and Edge */
    scrollbar-width: none;
    /* Firefox */
}
</style>
