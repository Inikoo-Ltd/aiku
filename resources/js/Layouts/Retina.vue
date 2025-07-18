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
import { faNarwhal, faCircle as falCircle, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faEnvelope, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faCodeBranch, faShoppingBasket, faCheck, faShoppingCart, faSignOutAlt, faTimes, faTimesCircle, faExternalLink, faSeedling, faSkull } from '@fal'
import { onMounted, provide, ref, watch } from 'vue'
import { useLocaleStore } from "@/Stores/locale"
import RetinaLayoutFulfilment from "./RetinaLayoutFulfilment.vue"
import RetinaLayoutDs from "./RetinaLayoutDs.vue"
import RetinaLayoutEcom from "./RetinaLayoutEcom.vue"
import { notify } from "@kyvg/vue3-notification"
import { usePage } from "@inertiajs/vue3"
import IrisHeader from "@/Layouts/Iris/Header.vue"
import IrisFooter from "@/Layouts/Iris/Footer.vue"
import { isArray } from "lodash-es"

import { confetti } from '@tsparticles/confetti'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle, faCheckCircle as fasCheckCircle, faInfoCircle, faTrashAlt, faCopy, faStickyNote } from "@fal"
import { faExclamationTriangle as fasExclamationTriangle, faCheckCircle, faExclamationCircle, faInfo, faCircle } from '@fas'
import Modal from "@/Components/Utils/Modal.vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faSearch, faBell, faPlus } from '@far'
import { faExclamationTriangle as fadExclamationTriangle } from '@fad'

library.add(fasExclamationTriangle, faExclamationTriangle, faTimesCircle, faExternalLink, faSeedling, faSkull, fasCheckCircle, faExclamationCircle, faInfo, faCircle, faInfoCircle, faTrashAlt, faCopy, faStickyNote)
library.add(fadExclamationTriangle, faCheckCircle, faNarwhal, falCircle, faHome, faBars, faUsersCog, faTachometerAltFast, faUser, faLanguage, faParachuteBox, faEnvelope, faCube, faBallot, faConciergeBell, faGarage, faAlignJustify, faShippingFast, faPaperPlane, faTasks, faCodeBranch, faShoppingBasket, faCheck, faShoppingCart, faSignOutAlt, faTimes, faSearch, faBell, faPlus)



provide('layout', useLayoutStore())
provide('locale', useLocaleStore())
initialiseRetinaApp()

const layout = useLayoutStore()

// Flash: Notification
watch(() => usePage().props?.flash?.notification, (notif) => {
    console.log('notif ret', notif)
    if (!notif) return

    notify({
        title: notif.title,
        text: notif.description ?? notif.message,
        type: notif.status,
    })
    // setTimeout(() => {
    // }, 500)
}, {
    deep: true,
    immediate: true
})


// Flash: Confetti
const defaults = {
    spread: 360,
    ticks: 50,
    gravity: 0,
    decay: 0.94,
    startVelocity: 30,
    shapes: ["star"],
    zIndex: 100,
};

const shootConfetti = () => {
    // console.log('1x')
    confetti('retina-confetti', {
        ...defaults,
        particleCount: 40,
        scalar: 1.2,
        shapes: ["star"],
    });

    confetti('retina-confetti', {
        ...defaults,
        particleCount: 10,
        scalar: 0.75,
        shapes: ["circle"],
    });
}

const shootMultipleConfetti = () => {
    setTimeout(() => {
        setTimeout(() => shootConfetti(), 0)
        setTimeout(() => shootConfetti(), 100)
        setTimeout(() => shootConfetti(), 200)
        setTimeout(() => shootConfetti(), 300)
    }, 500);
}
watch(() => usePage().props?.flash?.confetti, (newVal) => {
    console.log('confettixx ret', newVal)
    if (!newVal) return
    
    shootMultipleConfetti()
}, {
    deep: true,
    immediate: true
})

// Flash: GTM
watch(() => usePage().props?.flash?.gtm, (newValue) => {
    console.log('gtm ret', newValue)
    if (!newValue) return

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: newValue.event,
        ...newValue.data_to_submit,
    });
}, {
    deep: true,
    immediate: true
})

// Flash: Modal
interface Modal {
    title: string
    description: string
    type: 'success' | 'error' | 'info' | 'warning'
}
const selectedModal = ref<Modal | null>(null)
const isModalOpen = ref(false)
watch(() => usePage().props?.flash?.modal, (modal: Modal) => {
    console.log('modal ret', modal)
    if (!modal) return

    selectedModal.value = modal
    isModalOpen.value = true
}, {
    deep: true,
    immediate: true
})

// Section: To open/close the mobile menu
const isOpenMenuMobile = ref(false)
provide('isOpenMenuMobile', isOpenMenuMobile)


// Method: Hide the superchat widget
// const hideSuperchatWidget = () => {
//     const time = ref(0)
//     const xxInterval = setInterval(() => {
//         time.value += 150
//         const _superchatWidget = document.querySelector('#superchat-widget')
//         if (_superchatWidget) {
//             _superchatWidget.style.display = 'none'
//             clearInterval(xxInterval)
//             console.log('Cleared interval')
//         }

//         // To safety if GTM exist but don't have superchat
//         if (time.value > 10000) {
//             clearInterval(xxInterval)
//             console.log('Cleared interval due to timeout')
//         }
//     }, 150)
// }

onMounted(() => {
    // if (layout.iris?.is_have_gtm) {
    //     hideSuperchatWidget()
    // }
})


const getTextColorDependsOnStatus = (status: string) => {
    switch (status) {
        case 'success':
            return 'text-green-500'
        case 'error':
        case 'failure':
            return 'text-red-500'
        case 'warning':
            return 'text-yellow-500'
        case 'info':
            return 'text-gray-500'
        default:
            return ''
    }
}
const getBgColorDependsOnStatus = (status: string) => {
    switch (status) {
        case 'success':
            return 'bg-green-100'
        case 'error':
        case 'failure':
            return 'bg-red-100'
        case 'warning':
            return 'bg-yellow-100'
        case 'info':
            return 'bg-gray-100'
        default:
            return ''
    }
}
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
    <template v-else-if="layout.retina?.type === 'fulfilment'">
        <RetinaLayoutFulfilment v-if="layout.user">
            <template #default>
                <slot />
            </template>
        </RetinaLayoutFulfilment>

        <template v-else>
            <IrisHeader
                v-if="layout.iris?.header?.header"
                class="relative z-50 md:z-0"
                :data="layout.iris.header"
                :colorThemed="irisTheme"
                :menu="layout.iris.menu"
            />

            <slot />

            <IrisFooter
                v-if="layout.iris?.footer && !isArray(layout.iris.footer)"
                :data="layout.iris.footer"
                :colorThemed="irisTheme"
            />
        </template>
        <!-- <main v-else
            class="bg-gray-50 min-h-screen pt-16 pb-10 mx-auto flex justify-center transition-all px-8 lg:px-0"
        >
            <div class="bg-white border border-gray-300 w-full mx-auto max-w-5xl shadow-lg rounded-md h-fit relative flex flex-col text-gray-700">
                <slot name="default" />
            </div>
        </main> -->
    </template>

    <div v-else class="fixed inset-0 bg-slate-100 flex items-center justify-center">
        <slot />
    </div>

    <Modal :isOpen="isModalOpen" aonClose="isModalOpen = false" width="w-full max-w-lg">
        <div class="flex min-h-full items-end justify-center text-center sm:items-center px-2 py-3">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left transition-all w-full"
                :class="getTextColorDependsOnStatus(selectedModal?.status)"
            >
                <div>
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-gray-100"
                        :class="getBgColorDependsOnStatus(selectedModal?.status)"
                    >
                        <FontAwesomeIcon v-if="selectedModal?.status == 'error' || selectedModal?.status == 'failure'" icon='fal fa-times' class="text-red-500 text-2xl" fixed-width aria-hidden='true' />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'success'" icon='fal fa-check' class="text-green-500 text-2xl" fixed-width aria-hidden='true' />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'warning'" icon='fas fa-exclamation' class="text-orange-500 text-2xl" fixed aria-hidden='true' />
                        <FontAwesomeIcon v-if="selectedModal?.status == 'info'" icon='fas fa-info' class="text-gray-500 text-2xl" fixed-width aria-hidden='true' />
                    </div>
                    
                    <div class="mt-3 text-center sm:mt-5">
                        <div as="h3" class="font-semibold text-2xl">
                            {{ selectedModal?.title }}
                        </div>
                        <div v-if="selectedModal?.description" class="mt-2 text-sm opacity-75">
                            {{ selectedModal?.description }}
                        </div>
                        <div v-if="selectedModal?.message" class="mt-2 text-sm opacity-75">
                            {{ selectedModal?.message }}
                        </div>
                    </div>
                </div>

                <div class="mt-5 sm:mt-6">
                    <Button
                        @click="() => isModalOpen = false"
                        :label="trans('Ok, Get it')"
                        full
                    />
                </div>
            </div>
        </div>
    </Modal>

    <!-- Global declaration: Notification -->
    <notifications
        dangerously-set-inner-html
        :max="3"
        xwidth="500"
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

/* For Notification */
.custom-style-notification {
    @apply mt-2 bg-white rounded-md mr-3;

    .notification-title {
        @apply font-bold
    }

    .notification-content {
        @apply text-sm
    }

    &.success {
        @apply bg-lime-50 border-l-8 border border-lime-300 text-lime-600
    }
    &.warning {
        @apply bg-yellow-50 border-l-8 border border-yellow-400  text-amber-600
    }
    &.info {
        @apply bg-gray-100 border-l-8 border border-slate-500  text-slate-500
    }
    &.error {
        @apply bg-red-400 border-l-8 border border-red-600 text-white
    }
}

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
    background: v-bind('`linear-gradient(to top, #fcd34d, #fcd34d)`');

    &:hover,
    &:focus {
        color: #374151;
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}

.secondaryLink {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[6]}, ${layout.app.theme[6] + "AA"})`');

    &:hover,
    &:focus {
        color: v-bind('`${layout.app.theme[7]}`');
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}

// For icon box in FlatTreemap
.specialBox {
    background: v-bind('`linear-gradient(to top, ${layout.app.theme[0]}, ${layout.app.theme[0] + "AA"})`');
    color: v-bind('`${layout.app.theme[0]}`');
    border: v-bind('`4px solid ${layout.app.theme[0]}`');

    &:hover,
    &:focus {
        color: v-bind('`${layout.app.theme[1]}`');
    }

    @apply border-indigo-300 border-2 rounded-md cursor-pointer focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0em] motion-safe:transition-all motion-safe:duration-100 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1;
}

.vue-notification-group {
    width: 300px !important;

    @media (min-width: 640px) {
        width: 500px !important;;
    }
}

.p-message-text {
    width: 100%;
}

// Hide Checkout Apple Pay
#flow-container #googlepayAccordionContainer {
    display: none !important;
}

#retina-confetti {
    pointer-events: none;
}
</style>