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
        <div class="hidden md:block absolute bottom-full left-3" :class="layout.leftSidebar.show ? '' : 'px-2' " v-tooltip="layout.leftSidebar.show ? '' : `Reference: #${layout?.customer?.reference}`">
            <div v-if="layout.leftSidebar.show" class="text-xxs text-gray-500 -mb-1 italic">
                {{ trans("Customer reference:") }}
            </div>
            <div class=" text-xl text-[#1d252e] font-semibold flex items-center gap-2">
                <Transition name="slide-to-left">
                    <span v-if="layout.leftSidebar.show">#{{layout?.customer?.reference ?? '-'}}</span>
                </Transition>
                <FontAwesomeIcon 
                    v-if="layout.leftSidebar.show && layout?.customer?.reference" 
                    @click="useCopyText(layout?.customer?.reference)" 
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
            <div class="md:hidden bg-gray-100/50 text-white px-4 mb-3 flex justify-between items-center text-xs">
                <div>Language:</div>
                <SwitchLanguage>
                    <template #default="{ isLoadingChangeLanguage }">
                        <div class="underline text-xs py-2">
                            {{ Object.values(layout.iris.website_i18n?.language_options || {})?.find(language => language.code === layout.iris.website_i18n.current_language?.code)?.name }}
                            <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${layout.iris.website_i18n.current_language?.flag}`" xalt="language.code"   xtitle='capitalize(countryName)'  />
                        </div>
                    </template>
                </SwitchLanguage>
            </div>

            <div class="md:hidden bottom-full left-3 px-4 border-b border-gray-300/30 pb-1">
                <div class="text-xxs opacity-50 -mb-1 italic">
                    {{ trans("Customer reference:") }}
                </div>
                <div class=" text-xl font-semibold flex items-center gap-2">
                    <span>#{{layout?.customer?.reference ?? '-'}}</span>
                    <FontAwesomeIcon 
                        v-if="layout?.customer?.reference" 
                        @click="useCopyText(layout?.customer?.reference)" 
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
                    <div class="md:hidden mt-2 pt-6 border-t border-gray-300/40">
                        <ButtonWithLink
                            url="/app/logout"
                            method="post"
                            :data="{}"
                            type="negative"
                            :noHover="true"
                            full
                        >
                            <template #label="{ isLoadingVisit }">
                                <span class="w-full text-left">
                                    <FontAwesomeIcon v-if="!isLoadingVisit" icon="fal fa-sign-out" fixed-width aria-hidden="true" />
                                    {{ trans("Logout") }}
                                </span>
                            </template>
                        </ButtonWithLink>
                    </div>
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
