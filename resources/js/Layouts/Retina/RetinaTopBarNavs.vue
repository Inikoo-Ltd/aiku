<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 18 Feb 2024 06:51:44 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {get} from 'lodash-es';
import {capitalize} from "@/Composables/capitalize";
import { trans } from 'laravel-vue-i18n'
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faTerminal, faUserAlien, faCog, faGlobe, faWindowMaximize, faBriefcase, faPhotoVideo, faBrowser,
    faSign,faChartNetwork,faThumbsUp,faShippingFast

} from '@fal'
import { useLayoutStore } from "@/Stores/retinaLayout"


library.add(
    faTerminal, faUserAlien, faCog, faGlobe, faWindowMaximize, faBriefcase, faPhotoVideo,
    faBrowser,faSign,faChartNetwork,faThumbsUp,faShippingFast
)

const layout = useLayoutStore()
// console.log(layout.navigation?.[layout.currentParentModule]?.subNav)



</script>

<template>
    <div class="flex text-gray-400">
        <template v-if="layout.navigation?.[layout.currentParentModule]?.subNav">
            <Link
                v-for="menu in layout.navigation?.[layout.currentParentModule]?.subNav[layout.currentModule]?.topMenu?.subSections" :href="route(menu.route.name)"
                :id="get(menu,'label',menu.route.name)"
                class="group relative text-gray-600 group text-sm flex justify-end items-center cursor-pointer py-3 gap-x-2 px-4 md:px-4 lg:px-4"
                :title="capitalize(menu.tooltip??menu.label??'')"
            >
                <FontAwesomeIcon :icon="menu.icon"
                    class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out"
                    aria-hidden="true"/>
                <span v-if="menu.label" class="hidden lg:inline capitalize whitespace-nowrap">{{ menu.label }}</span>

                <!-- The line appear on hover and active state -->
                <div :class="[route(layout.currentRoute, route().v().params).includes(route(menu.route.name)) ? 'bottomNavigationActiveCustomer' : 'bottomNavigationCustomer']" />
            </Link>
        </template>

        <template v-else-if="layout.navigation?.[layout.currentModule]?.topMenu.subSections">
            <Link
                v-for="menu in layout.navigation?.[layout.currentModule]?.topMenu.subSections" :href="route(menu.route.name)"
                :id="get(menu,'label',menu.route.name)"
                class="group relative text-gray-600 group text-sm flex justify-end items-center cursor-pointer py-3 gap-x-2 px-4 md:px-4 lg:px-4"
                :title="capitalize(menu.tooltip??menu.label??'')"
            >
                <FontAwesomeIcon :icon="menu.icon"
                    class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out"
                    aria-hidden="true"/>
                <span v-if="menu.label" class="hidden lg:inline capitalize whitespace-nowrap">{{ trans(menu.label) }}</span>
                <!-- The line appear on hover and active state -->
                <div :class="[route(layout.currentRoute, route().v().params).includes(route(menu.route.name)) ? 'bottomNavigationActiveCustomer' : 'bottomNavigationCustomer']" />
            </Link>
        </template>
        <template v-else>
            {{ layout.navigation?.[layout.currentParentModule]?.topMenu.subSections}}
            <Link
                v-for="menu in layout.navigation?.[layout.currentModule]?.topMenu.subSections" :href="route(menu.route.name)"
                :id="get(menu,'label',menu.route.name)"
                class="group relative text-gray-600 group text-sm flex justify-end items-center cursor-pointer py-3 gap-x-2 px-4 md:px-4 lg:px-4"
                :title="capitalize(menu.tooltip??menu.label??'')"
            >
                <FontAwesomeIcon :icon="menu.icon"
                    class="h-5 lg:h-3.5 w-auto group-hover:opacity-100 opacity-70 transition duration-100 ease-in-out"
                    aria-hidden="true"/>
                <span v-if="menu.label" class="hidden lg:inline capitalize whitespace-nowrap">{{ trans(menu.label) }}</span>
                <!-- The line appear on hover and active state -->
                <div :class="[route(layout.currentRoute, route().v().params).includes(route(menu.route.name)) ? 'bottomNavigationActiveCustomer' : 'bottomNavigationCustomer']" />
            </Link>
        </template>

    </div>

</template>

