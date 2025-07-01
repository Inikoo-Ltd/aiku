<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { provide, ref } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { useColorTheme } from "@/Composables/useStockList"
import { isArray } from 'lodash-es'

import IrisHeader from "@/Layouts/Iris/Header.vue"
import IrisFooter from "@/Layouts/Iris/Footer.vue"
import RetinaDsLeftSidebar from "./Retina/RetinaDsLeftSidebar.vue"
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"

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
	faListUl, faEye
)
import { faListUl, faEye } from "@far"

import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue"
import { trans } from "laravel-vue-i18n"
library.add(faShoppingBasket, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder)

const layout = useLayoutStore()
const locale = useLocaleStore()

provide("layout", layout)
provide("locale", locale)

const { props } = usePage()
const irisTheme = props?.iris?.theme ?? { color: [...useColorTheme[2]] }

const sidebarOpen = ref(false)

console.log("Layout Ds", layout.iris.is_logged_in)
</script>

<template>
	<!-- page background -->
	<div class="-z-[1] fixed inset-0 bg-slate-100" />

	<ScreenWarning v-if="layout.app.environment === 'staging'">
		{{ trans("This environment is for testing and development purposes only. The data you enter will be deleted in the future.") }}
	</ScreenWarning>

	<div
		class="isolate relative min-h-screen transition-all"
		:class="{
			'mr-44': Object.values(layout.rightSidebar || {}).some((v) => v.show),
			'mr-0': !Object.values(layout.rightSidebar || {}).some((v) => v.show),
		}">
		<!-- header always on top -->
		<IrisHeader
			v-if="layout.iris?.header?.header"
			class="relative z-50 md:z-0"
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
				class="flex flex-col md:flex-row gap-x-2 max-w-5xl lg:max-w-7xl w-full lg:mx-auto my-10 px-8 xl:px-0 transition-all">
				<RetinaDsLeftSidebar
					v-if="layout.user"
					:class="[
						'fixed inset-y-0 left-0 md:h-fit bg-white shadow-lg transform z-50 md:z-0 transition-all',
						sidebarOpen ? 'translate-x-0' : '-translate-x-full',
						'md:relative md:translate-x-0 md:flex md:flex-col',
						layout.leftSidebar.show ? 'min-w-56 w-56' : 'min-w-14 w-14 '
					]"
				/>

				<!-- RetinaLayoutDS -->
				<div class="flex-1 flex flex-col pb-6 text-gray-700 relative">
					<div class="flex justify-between items-end absolute bottom-full w-full border-b-0 mx-auto transition-all mb-1">
						<Breadcrumbs
							class=""
							:breadcrumbs="usePage().props.breadcrumbs ?? []"
							:navigation="usePage().props.navigation ?? []"
							:layout="layout"
							style="max-width: calc(1280px - 200px)"
						/>

						<Link
							v-if="layout.iris?.is_logged_in"
							:href="route('retina.top_up.dashboard')"
							class="bg-white border border-gray-300 px-4 py-0.5 rounded-full flex items-center gap-x-2 xtext-indigo-600"
						>
							<!-- <FontAwesomeIcon icon="fal fa-money-bill-wave " class="" fixed-width aria-hidden="true" /> -->
							{{ trans("My balance") }}:
							<span class="font-semibold tabular-nums">
								{{ locale.currencyFormat(layout.retina?.currency?.code, layout.retina?.balance || 0)}}
							</span>
						</Link>
					</div>
					
					<div
						xstyle="max-width: calc(1280px - 200px)"
						class="pb-6 bg-white w-full mx-auto shadow-lg rounded-lg">
						<div id="RetinaTopBarSubsections" class="pl-2 py-2 flex gap-x-2" />

						<!-- Main content of the page -->
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
