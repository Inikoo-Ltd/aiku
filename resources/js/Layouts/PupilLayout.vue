<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { useLayoutStore } from "@/Stores/pupilLayout"
import Notification from '@/Components/Utils/Notification.vue'
import Breadcrumbs from "@/Components/Navigation/Breadcrumbs.vue"
import { provide, ref, watch } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
// import RetinaLayoutFulfilment from "./RetinaLayoutFulfilment.vue"
// import RetinaLayoutDs from "./RetinaLayoutDs.vue"
// import RetinaLayoutEcom from "./RetinaLayoutEcom.vue"
import { initialisePupilApp } from "@/Composables/initialisePupilApp"
import { notify } from "@kyvg/vue3-notification"
import { usePage } from "@inertiajs/vue3"
// import IrisHeader from "@/Layouts/Iris/Header.vue"
// import IrisFooter from "@/Layouts/Iris/Footer.vue"
import RetinaDsLeftSidebar from "./Retina/RetinaDsLeftSidebar.vue"

import { faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faMoneyBillWave, faListUl } from '@fal'
import { faSearch, faBell } from '@far'
import { faCheckCircle } from '@fas'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
library.add(faCheckCircle, faNarwhal, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faMoneyBillWave, faSearch, faBell )


provide('layout', useLayoutStore())
provide('locale', useLocaleStore())
initialisePupilApp()

const layout = useLayoutStore()
const sidebarOpen = ref(false)

watch(() => usePage().props?.flash?.notification, (notif) => {
    console.log('notif ret', notif)
    if (!notif) return

    notify({
        title: notif.title,
        text: notif.description,
        type: notif.status,
    })
})
</script>

<template>
    <div class="-z-[1] absolute inset-0 bg-slate-100" />

	<div
		class="isolate relative transition-all"
		:class="{
			// 'mr-44': Object.values(layout.rightSidebar || {}).some((v) => v.show),
			// 'mr-0': !Object.values(layout.rightSidebar || {}).some((v) => v.show),
		}">
        
		<!-- <IrisHeader
			class="relative z-50 md:z-0"
			v-if="layout.iris?.header?.header"
			:data="layout.iris.header"
			:colorThemed="irisTheme"
			:menu="layout.iris.menu" /> -->

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
				class="flex flex-col md:flex-row gap-x-4 max-w-7xl w-full mx-auto my-10 px-8 xl:px-0 transition-all">
				<RetinaDsLeftSidebar
					v-if="layout.user"
					:class="[
						'fixed inset-y-0 left-0 w-auto md:h-fit bg-white shadow-lg transform transition-transform z-50 md:z-0',
						sidebarOpen ? 'translate-x-0' : '-translate-x-full',
						'md:relative md:translate-x-0 md:flex md:flex-col',
					]" />

				<!-- your actual page content -->
				<div class="flex-1 flex flex-col pb-6 text-gray-700 relative">
					<Breadcrumbs
						class="absolute bottom-full w-full border-b-0 mx-auto transition-all mb-1"
						:breadcrumbs="usePage().props.breadcrumbs ?? []"
						:navigation="usePage().props.navigation ?? []"
						:layout="layout"
						style="max-width: calc(1280px - 200px)" />
					<div
						style="max-width: calc(1280px - 200px)"
						class="pb-6 bg-white w-full mx-auto shadow-lg rounded-lg">
						<div id="RetinaTopBarSubsections" class="pl-2 py-2 flex gap-x-2" />
						<slot name="default" />
					</div>
				</div>
			</main>
		</div>

		<!-- <IrisFooter
			v-if="layout.iris?.footer && !isArray(layout.iris.footer)"
			:data="layout.iris.footer"
			:colorThemed="irisTheme" /> -->
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