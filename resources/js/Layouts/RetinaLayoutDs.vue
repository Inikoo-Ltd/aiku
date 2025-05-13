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
import { faListUl } from "@far"

initialiseRetinaApp()
library.add(faShoppingBasket, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder)

provide("layout", useLayoutStore())
provide("locale", useLocaleStore())

const layout = useLayoutStore()
const { props } = usePage()
const irisTheme = props?.iris?.theme ?? { color: [...useColorTheme[2]] }

const sidebarOpen = ref(false)
</script>

<template>
	<!-- page background -->
	<div class="-z-[1] fixed inset-0 bg-slate-100" />

	<div
		class="isolate relative min-h-screen transition-all"
		:class="{
			'mr-44': Object.values(layout.rightSidebar || {}).some((v) => v.show),
			'mr-0': !Object.values(layout.rightSidebar || {}).some((v) => v.show),
		}">
		<!-- header always on top -->
		<IrisHeader
			class="relative z-50"
			v-if="layout.iris?.header?.header"
			:data="layout.iris.header"
			:colorThemed="irisTheme"
			:menu="layout.iris.menu" />

		<!-- wrapper for mobile overlay + content -->
		<div class="relative">
			<!-- Floating menu button (mobile only) -->
			<button
				@click="sidebarOpen = !sidebarOpen"
				class="fixed bottom-4 right-4 z-50 md:hidden bg-indigo-600 hover:bg-indigo-700 text-white p-4 rounded-full shadow-lg focus:outline-none"
				aria-label="Toggle menu">
				<FontAwesomeIcon
					:icon="faListUl"
					class="text-white"
					fixed-width
					aria-hidden="true" />
			</button>

			<div
				v-if="sidebarOpen"
				@click="sidebarOpen = false"
				class="fixed inset-0 bg-gray-800/50 z-40 md:hidden" />

			<!-- sidebar + main content -->
			<main
				class="flex flex-col md:flex-row gap-x-2 max-w-7xl w-full mx-auto my-10 px-8 xl:px-0 transition-all">
				<RetinaDsLeftSidebar
					v-if="layout.user"
					:class="[
						'fixed inset-y-0 left-0 w-auto md:h-fit bg-white shadow-lg transform transition-transform z-50',
						sidebarOpen ? 'translate-x-0' : '-translate-x-full',
						'md:relative md:translate-x-0 md:flex md:flex-col',
					]" />

				<!-- your actual page content -->
				<div class="flex-1 flex flex-col pb-6 text-gray-700 relative">
					<div id="RetinaTopBarSubsections" class="pl-2 py-2 flex gap-x-2" />
					<div
						style="max-width: calc(1280px - 200px)"
						class="pb-6 bg-white w-full mx-auto shadow-lg rounded-lg">
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
