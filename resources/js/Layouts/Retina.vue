<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 18 Feb 2024 06:25:44 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { initialiseRetinaApp } from "@/Composables/initialiseRetinaApp"
import { useLayoutStore } from "@/Stores/retinaLayout"
import Notification from '@/Components/Utils/Notification.vue'
import { faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks } from '@fal'
import { faSearch, faBell } from '@far'
import { provide } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
import RetinaLayoutFulfilment from "./RetinaLayoutFulfilment.vue"
import RetinaLayoutDs from "./RetinaLayoutDs.vue"
import RetinaLayoutEcom from "./RetinaLayoutEcom.vue"

library.add( faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faSearch, faBell )


provide('layout', useLayoutStore())
provide('locale', useLocaleStore())
initialiseRetinaApp()

const layout = useLayoutStore()

</script>

<template>
    <!-- Retina: Ds -->
    <RetinaLayoutDs
        v-if="layout.retina?.type === 'dropshipping'"
    >
        <template #default>
            <slot />
        </template>
    </RetinaLayoutDs>

    <!-- Retina: Ecom -->
    <RetinaLayoutEcom
        v-else-if="layout.retina?.type === 'b2b'"
    >
        <template #default>
            <slot />
        </template>
    </RetinaLayoutEcom>
    
    <!-- Retina: Fulfilment -->
    <RetinaLayoutFulfilment
        v-else-if="layout.retina?.type === 'fulfilment'"
    >
        <template #default>
            <slot />
        </template>
    </RetinaLayoutFulfilment>

    <div v-else class="fixed inset-0 bg-slate-100 flex items-center justify-center">
        <slot />
    </div>

    <!-- Global declaration: Notification -->
    <notifications
        dangerously-set-inner-html
        :max="3"
        width="500"
        classes="custom-style-notification"
        :pauseOnHover="true"    
    >
        <template #body="props">
            <Notification :notification="props" />  
        </template>
    </notifications>
</template>

<style lang="scss">
// * {
//     --color-primary: v-bind('layout.app.theme[0]');
// }

/* Navigation: Aiku */
.navigationActive {
    @apply rounded py-2 font-semibold transition-all duration-0 ease-out;
}
.navigation {
    @apply hover:bg-gray-300/40 py-2 rounded font-semibold transition-all duration-0 ease-out;
}

.subNavActive {
    @apply bg-indigo-200/20 sm:border-l-4 sm:border-indigo-100 text-white font-semibold transition-all duration-0 ease-in-out;
}
.subNav {
    @apply hover:bg-white/80 text-gray-100 hover:text-indigo-500 font-semibold transition-all duration-0 ease-in-out
}

.navigationSecondActive {
    @apply transition-all duration-100 ease-in-out;
}
.navigationSecond {
    @apply hover:bg-gray-100 text-gray-400 hover:text-gray-500 transition-all duration-100 ease-in-out
}

.bottomNavigationActive {
    @apply w-5/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all;
    background-color: v-bind('layout.app.theme[4]');
}
.bottomNavigation {
    @apply bg-gray-300 w-0 group-hover:w-3/6 absolute h-0.5 rounded-full bottom-0 left-[50%] translate-x-[-50%] mx-auto transition-all
}

.primaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[2]}, ${layout.app.theme[2] + "AA"})`');
    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[3]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5
}

.secondaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "AA"})`');
    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1 py-0.5
}

// For icon box in FlatTreemap
.specialBox {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[0]}, ${layout.app.theme[0] + "AA"})`');
    color: v-bind('`${layout.app.theme[0]}`');
    border: v-bind('`4px solid ${layout.app.theme[0]}`');

    &:hover, &:focus {
        color: v-bind('`${layout.app.theme[1]}`');
    }

    @apply border-indigo-300 border-2 rounded-md
    cursor-pointer
    focus:ring-0 focus:outline-none focus:border-none
    bg-no-repeat [background-position:0%_100%]
    [background-size:100%_0em]
    motion-safe:transition-all motion-safe:duration-100
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%] px-1;
}
</style>