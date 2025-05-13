<script setup lang="ts">
import { usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { provide, ref } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { useColorTheme } from "@/Composables/useStockList"
import { isArray } from "lodash"

import IrisHeader from "@/Layouts/Iris/Header.vue"
import IrisFooter from "@/Layouts/Iris/Footer.vue"
import RetinaDsLeftSidebar from "./Retina/RetinaDsLeftSidebar.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faShoppingBasket,
	faFax,
	faCog,
	faUserCircle,
	faMoneyBillWave,
	faFolder,
	faBuilding,
	faCreditCard,
    faEllipsisV,
} from "@fal"
import { faArrowRight, faExclamationCircle, faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(
	faShoppingBasket,
	faFax,
	faCog,
	faUserCircle,
	faMoneyBillWave,
	faFolder,
	faBuilding,
	faCreditCard,
	faExclamationCircle,
	faCheckCircle,
	faArrowRight,
    faListUl
)
import { initialiseRetinaApp } from "@/Composables/initialiseRetinaApp"
initialiseRetinaApp()
library.add(faShoppingBasket, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder)

provide("layout", useLayoutStore())
provide("locale", useLocaleStore())

const layout = useLayoutStore()
const { props } = usePage()
const irisTheme = props?.iris?.theme ?? { color: [...useColorTheme[2]] }

const sidebarOpen = ref(false)


console.log('LayoutDs')
</script>

<template>
    <div class="-z-[1] fixed inset-0 bg-slate-100" />
    <div class="isolate relative min-h-full transition-all"
        :class="[Object.values(layout.rightSidebar).some(value => value.show) ? 'mr-44' : 'mr-0']">
        <IrisHeader
            v-if="layout.iris?.header?.header"
            :data="layout.iris?.header"
            :colorThemed="irisTheme"
            :menu="layout.iris?.menu"
        />
        
        <!-- Sidebar: Left -->
        <div class="">
            <div @click="sidebarOpen = !sidebarOpen" class="bg-gray-200/80 fixed top-0 w-screen h-screen z-10 md:hidden" v-if="sidebarOpen" />
            
        </div>

        <!-- Main Content -->
        <main
            class="flex gap-x-2 max-w-7xl w-full mx-auto min-h-[500px] h-fit my-10 transition-all px-8 xl:px-0"
        >
            <RetinaDsLeftSidebar
                v-if="layout.user"
                class="z-20 block h-fit right-full -translate-x-3 w-48 xabsolute xpb-20 xpx-2 md:flex md:flex-col  transition-all"
                @click="sidebarOpen = !sidebarOpen"
            />

            <div class="w-full min-h-full h-full relative flex flex-col text-gray-700">
                <!-- Section: Subsections (Something will teleport to this section) -->
                  <Breadcrumbs
                    class="absolute bottom-full w-full border-b-0 mx-auto transition-all mb-1"
                    :breadcrumbs="usePage().props.breadcrumbs ?? []"
                    :navigation="usePage().props.navigation ?? []"
                    :layout="layout"
                    style="max-width: calc(1280px - 200px);"
                />

                <div style="max-width: calc(1280px - 200px);" class="pb-6 bg-white w-full mx-auto shadow-lg rounded-lg">
                <div id="RetinaTopBarSubsections" class="pl-2 flex gap-x-2 h-full" />
                    <slot name="default" />
                </div>
            </div>
        </main>

    </div>

		<IrisFooter
			v-if="layout.iris?.footer && !isArray(layout.iris.footer)"
			:data="layout.iris.footer"
			:colorThemed="irisTheme" />
	</div>
</template>

<style lang="scss" scoped>
:deep(.topbarNavigationActive) {
	@apply transition-all duration-100 rounded-md py-1.5 pl-2 pr-3;
	background-color: v-bind("layout.app.theme[4]");
	color: v-bind("layout.app.theme[5]");
}

:deep(.topbarNavigation) {
	@apply transition-all duration-100 rounded-md py-1.5 pl-2 pr-3;
	&:hover {
		background-color: v-bind("layout.app.theme[4] + '25'");
	}
}

#RetinaTopBarSubsections:has(> *) {
	@apply pb-2;
}
</style>
