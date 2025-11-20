<script setup lang="ts">
import { Link, usePage } from "@inertiajs/vue3"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { inject, provide, ref } from "vue"
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

import { trans } from "laravel-vue-i18n"
import BreadcrumbsIris from "@/Components/Navigation/BreadcrumbsIris.vue"
import RetinaBottomNavigation from "./Retina/RetinaBottomNavigation.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue";
import Modal from "@/Components/Utils/Modal.vue";
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
library.add(faShoppingBasket, faFax, faCog, faUserCircle, faMoneyBillWave, faFolder)

const layout = useLayoutStore()
const locale = useLocaleStore()
const isOpenMenuMobile = ref(false)
provide("layout", layout)
provide("locale", locale)
provide('isOpenMenuMobile', isOpenMenuMobile)
const { props } = usePage()
const isOpenModalCreditCard = ref(props.retina.show_cards_modal)
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

	<div class="isolate relative transition-all pb-12 md:pb-0"
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
			:menu="layout.iris.menu"
		/>

		<!-- wrapper for mobile overlay + content -->
		<div class="relative">

			<!-- sidebar + main content -->
			<main
				class="flex flex-col md:flex-row gap-x-2 lg:max-w-7xl w-full lg:mx-auto my-2 md:my-10 px-3 md:px-8 xl:px-0 transition-all">
				<Transition>
					<RetinaDsLeftSidebar
						v-if="layout.user && layout.iris.is_logged_in && screenType !== 'mobile'"
						:class="[
							'fixed inset-y-0 left-0 md:h-fit bg-white shadow-lg transform z-50 md:z-0 transition-all',
							sidebarOpen ? 'translate-x-0' : '-translate-x-full',
							'md:relative md:translate-x-0 md:flex md:flex-col',
							layout.leftSidebar.show ? 'min-w-56 w-1/2 md:w-56' : 'min-w-56 w-56 md:min-w-14 md:w-14 '
						]"
					/>
				</Transition>

				<!-- RetinaLayoutDS -->
				<div class="flex-1 flex flex-col pb-6 text-gray-700 relative">
					<div class="z-[1] flex flex-col md:flex-row md:justify-between md:items-end md:absolute bottom-full w-full border-b-0 mx-auto transition-all mb-1">
						<div>
							<BreadcrumbsIris
								class=""
								:breadcrumbs="usePage().props.breadcrumbs ?? []"
								:navigation="usePage().props.navigation ?? []"
								:layout="layout"
								style="max-width: calc(1280px - 200px)"
							/>
						</div>

						<Link
							v-if="layout.iris?.is_logged_in"
							:href="route('retina.top_up.dashboard')"
							class="place-self-end bg-pink-100 border border-pink-300 text-sm px-3 md:px-4 md:py-0.5 rounded-full w-fit flex items-center gap-x-2 xtext-indigo-600"
						>
							<!-- <FontAwesomeIcon icon="fal fa-money-bill-wave " class="" fixed-width aria-hidden="true" /> -->
							{{ trans("My balance") }}:
							<span class="font-semibold tabular-nums">
								{{ locale.currencyFormat(layout.retina?.currency?.code, layout.retina?.balance || 0)}}
							</span>
						</Link>
					</div>

					<div
						class="pb-6 bg-white w-full mx-auto shadow-lg rounded-lg">
						<div id="RetinaTopBarSubsections" class="pl-2 py-2 flex gap-x-2 overflow-x-auto" />

						<!-- Main content of the page -->
						<slot name="default" />
					</div>
				</div>
			</main>
		</div>


        <Modal :isOpen="isOpenModalCreditCard" @onClose="isOpenModalCreditCard = false" width="w-[600px]">
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl mb-2">{{ trans("Add Credit Card") }}</h2>
                    <p class="text-sm leading-5 text-gray-400">
                        {{ trans("Important Update: To ensure the fastest and most seamless experience with our fully automated order processing system via our sales channels, we strongly recommend saving your payment card in your account. This allows your future orders to be processed instantly and without any manual delays") }}
                    </p>
                </div>

                <div class="mt-6 mb-4 relative">
                    <ButtonWithLink @click="isOpenModalCreditCard = false" :url="route('retina.dropshipping.mit_saved_cards.dashboard')" label="Add Credit Card" full />
                </div>
            </div>
        </Modal>

		<IrisFooter
			v-if="layout.iris?.footer && !isArray(layout.iris.footer)"
			:data="layout.iris.footer"
			:colorThemed="irisTheme"
		/>


		<!-- Section: bottom navigation -->
		<div v-if="layout.user && screenType === 'mobile'" class="bg-[rgb(20,20,20)] text-white fixed bottom-0 w-full z-10">
			<RetinaBottomNavigation

			/>
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
