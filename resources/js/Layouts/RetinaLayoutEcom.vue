<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { inject, onMounted, provide, ref } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { useColorTheme } from "@/Composables/useStockList"
import { isArray } from 'lodash-es'
import { generateNavigationName } from '@/Composables/useConvertString'

import IrisHeader from "@/Layouts/Iris/Header.vue"
import IrisFooter from "@/Layouts/Iris/Footer.vue"
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShoppingBasket, faHandHoldingUsd, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder, faBuilding, faCreditCard, faBooks } from "@fal"
import { faArrowRight, faExclamationCircle, faCheckCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add( faShoppingBasket, faHandHoldingUsd, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder, faBuilding, faCreditCard, faBooks, faExclamationCircle, faCheckCircle, faArrowRight, faListUl, faEye )
import { faListUl, faEye } from "@far"

import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue"
import { trans } from "laravel-vue-i18n"
import RetinaEcomLeftSidebar from "./Retina/RetinaEcomLeftSidebar.vue"
import RetinaMobileNavigationSimple from "./Retina/RetinaMobileNavigationSimple.vue"
import BreadcrumbsIris from "@/Components/Navigation/BreadcrumbsIris.vue"
library.add(faShoppingBasket, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder)

const layout = useLayoutStore()
const locale = useLocaleStore()
const isOpenMenuMobile = ref(false)
provide("layout", layout)
provide("locale", locale)
provide('isOpenMenuMobile', isOpenMenuMobile)
const { props } = usePage()
const irisTheme = props?.iris?.theme ?? { color: [...useColorTheme[2]] }

const sidebarOpen = ref(false)

const screenType = inject('screenType', ref<'mobile' | 'tablet' | 'desktop'>('desktop'))

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

			<!-- sidebar + main content -->
			<main class="flex flex-col md:flex-row gap-x-2 lg:max-w-7xl w-full lg:mx-auto my-10 px-4 lg:px-8 xl:px-0 transition-all">
				<RetinaEcomLeftSidebar
					v-if="layout.user && screenType !== 'mobile'"
					:class="[
						'fixed inset-y-0 left-0 md:h-fit bg-white shadow-lg transform z-50 md:z-0 transition-all',
						sidebarOpen ? 'translate-x-0' : '-translate-x-full',
						'md:relative md:translate-x-0 md:flex md:flex-col',
						layout.leftSidebar.show ? 'min-w-56 w-56' : 'min-w-56 w-56 md:min-w-14 md:w-14 '
					]"
				/>

				<!-- RetinaLayoutDS -->
				<div class="flex-1 flex flex-col pb-6 text-gray-700 relative">
					<div class="overflow-x-auto flex flex-col md:flex-row md:justify-between md:items-end absolute bottom-full w-full border-b-0 mx-auto transition-all mb-1">
						<!-- <Breadcrumbs
							class=""
							:breadcrumbs="usePage().props.breadcrumbs ?? []"
							:navigation="usePage().props.navigation ?? []"
							:layout="layout"
							style="max-width: calc(1280px - 200px)"
						/> -->

						<div v-if="layout.iris?.is_logged_in"
							class="xbg-slate-300 xborder border-slate-500 px-4 py-0.5 rounded-full flex items-center gap-x-2 xtext-indigo-600"
						>
							{{ trans("Reference") }}:
							<span class="font-semibold tabular-nums">
								#{{ layout?.iris?.customer?.reference }}
							</span>
						</div>
					</div>

					<div class="overflow-x-auto w-full">
						<div class="relative pb-6 bg-white shadow-lg rounded-lg">
							<!-- Section: Top navigation -->
							<div id="RetinaTopBarSubsections" class="pl-2 py-2 flex gap-x-2 overflow-x-auto" />

							<!-- Main content of the page -->
							<slot name="default" />
						</div>

						<div id="retina-end-of-main" class="w-full mt-6" />
					</div>
				</div>
			</main>
		</div>

		<IrisFooter
			v-if="layout.iris?.footer && !isArray(layout.iris.footer)"
			:data="layout.iris.footer"
			:colorThemed="irisTheme"
		/>
		
		<!-- Section: bottom navigation -->
		<div v-if="layout.user && screenType === 'mobile'" class="bg-[rgb(20,20,20)] text-white sticky bottom-0 w-full z-10">
			<div class="flex gap-x-3 pt-2 pb-3 px-4 overflow-x-auto">
				<template v-for="(grpNav, itemKey) in layout.navigation">
					<RetinaMobileNavigationSimple
						:nav="grpNav"
						:navKey="generateNavigationName(itemKey)"
					/>
				</template>
			</div>
		</div>
	</div>
</template>

<style lang="scss">
@media (max-width: 767px) {
	#launcher {
		// Widget: Help chat (JIRA)
		bottom: 50px !important;
	}
	#cookiescript_badge {
		// Widget: Cookies acceptation
		bottom: 70px !important;
	}
	#superchat-widget-content-root {
		// Widget: Superchat
		bottom: 45px !important;
	}
}
</style>


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
