<script setup lang="ts">
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import CustomerShowcase from '@/Components/Showcases/Grp/CustomerShowcase.vue'
import { useTabChange } from '@/Composables/tab-change'
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { trans } from 'laravel-vue-i18n'
import Fieldset from 'primevue/fieldset'
import { computed, ref } from 'vue'
// import {  } from 'vue'
import type { Component } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShopify, faTiktok } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
// library.add(faShopify)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    }
    showcase: {
        stats: {
            name: string
            number_orders: number
            number_customer_clients: number
            number_portfolios: number
        }
    }
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: CustomerShowcase,

    }

    return components[currentTab.value]
})
</script>

<template>
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <!-- <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"  /> -->
    <!-- {{ showcase }} -->

    <div class="p-6">
        <div :legend="trans('Statistic')" class="border border-gray-300 rounded-lg w-full sm:max-w-lg">
            <div v-if="route().params.platform !== 'manual'" class="py-3 px-2 flex items-center justify-between gap-x-4 w-full border-b border-gray-900/15">
                <dl v-if="true" class="flex-auto pl-3">
                    <dt class="text-xs text-gray-400">{{ trans("Account name") }}</dt>
                    <dd v-tooltip="trans('Account name')" class="w-fit mt text-xl font-semibold leading-6">
                        {{ showcase.stats.name }}
                    </dd>
                </dl>

                <div class="px-6 text-4xl">
                    <FontAwesomeIcon v-if="route().params.platform === 'shopify'" v-tooltip="'Shopify'" :icon="faShopify" class="" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-if="route().params.platform === 'tiktok'" v-tooltip="'Tiktok'" :icon="faTiktok" class="" fixed-width aria-hidden="true" />
                    <svg v-if="route().params.platform === 'woocommerce'" version="1.0" xmlns="http://www.w3.org/2000/svg"
                        width="40" height="40" viewBox="0 0 440.000000 440.000000"
                        preserveAspectRatio="xMidYMid meet">

                        <g transform="translate(0.000000,440.000000) scale(0.100000,-0.100000)"
                        fill="currentFill" stroke="none">
                        <path d="M483 3336 c-106 -34 -195 -115 -235 -215 l-23 -56 -3 -653 c-2 -423
                        1 -673 8 -709 22 -120 91 -213 198 -265 l67 -33 810 -3 810 -3 340 -188 c187
                        -104 341 -186 342 -183 2 4 -31 88 -71 187 l-75 180 627 5 627 5 67 33 c81 40
                        148 110 180 190 l23 57 0 690 0 690 -22 55 c-33 81 -101 153 -181 192 l-67 33
                        -1690 2 c-1378 1 -1698 -1 -1732 -11z m1504 -213 c44 -20 73 -66 73 -114 0
                        -20 -13 -65 -29 -100 -101 -222 -191 -749 -191 -1119 0 -144 -13 -180 -77
                        -206 -54 -23 -106 0 -184 83 -112 120 -219 315 -295 537 l-37 108 -136 -268
                        c-147 -290 -197 -372 -262 -428 -72 -61 -135 -44 -182 49 -61 123 -153 511
                        -237 1000 -50 289 -51 314 -7 361 69 76 204 58 231 -31 3 -11 21 -115 41 -231
                        34 -209 79 -442 113 -583 l17 -73 179 338 c98 187 187 351 198 367 25 36 68
                        57 114 57 27 0 44 -8 69 -33 35 -35 38 -44 79 -235 26 -117 63 -249 103 -357
                        17 -47 18 -48 21 -20 9 96 47 336 69 435 50 225 136 435 190 464 37 20 97 20
                        140 -1z m648 -168 c123 -33 207 -100 265 -214 49 -98 63 -176 58 -331 -4 -118
                        -9 -147 -37 -230 -62 -186 -172 -330 -298 -391 -63 -32 -74 -34 -168 -34 -92
                        0 -106 3 -167 32 -88 41 -148 98 -192 182 -54 103 -69 181 -63 336 5 156 21
                        216 88 354 41 83 65 118 122 175 121 122 243 159 392 121z m1020 -1 c118 -30
                        203 -101 260 -215 140 -276 36 -725 -210 -911 -131 -99 -318 -102 -463 -9 -84
                        55 -139 137 -178 266 -25 83 -25 292 0 390 52 201 179 384 316 456 72 38 184
                        47 275 23z"/>
                        <path d="M2500 2683 c-35 -13 -70 -46 -104 -97 -100 -152 -119 -332 -50 -475
                        47 -96 120 -102 212 -18 81 75 126 198 125 342 0 179 -80 287 -183 248z"/>
                        <path d="M3494 2671 c-143 -87 -218 -371 -142 -541 32 -70 57 -92 106 -94 159
                        -4 290 302 223 522 -36 120 -106 162 -187 113z"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Section: Field -->
            <div class="flex flex-col gap-y-2 w-full py-4 px-5">
                <!-- Field: Number orders -->
                <dl v-if="showcase.stats.number_orders > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Orders')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_orders }}</dd>
                </dl>

                <!-- Field: Number customer clients -->
                <dl v-if="showcase.stats.number_customer_clients > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Customer clients')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-users" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_customer_clients }}</dd>
                </dl>

                <!-- Field: Number portfolios -->
                <dl v-if="showcase.stats.number_portfolios > -1" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Portfolios')" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">{{ showcase.stats.number_portfolios }}</dd>
                </dl>

                <!-- Field: Created at -->
                <dl v-if="data?.customer?.created_at" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Created at')" class="flex-none">
                        <span class="sr-only">Created at</span>
                        <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <time datetime="2023-01-31">{{ useFormatTime(data?.customer?.created_at) }}</time>
                    </dd>
                </dl>
                <!-- Field: Email -->
                <dl v-if="data?.customer?.email" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Email')" class="flex-none">
                        <span class="sr-only">Email</span>
                        <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <a :href="`mailto:${data.customer.email}`">{{ data?.customer?.email }}</a>
                    </dd>
                </dl>
                <!-- Field: Phone -->
                <dl v-if="data?.customer?.phone" class="flex items-center w-full flex-none gap-x-4">
                    <dt v-tooltip="trans('Phone')" class="flex-none">
                        <span class="sr-only">Phone</span>
                        <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                        <a :href="`tel:${data.customer.email}`">{{ data?.customer?.phone }}</a>
                    </dd>
                </dl>
                <!-- Field: Address -->
                <dl v-if="data?.customer?.address" class="relative flex items w-full flex-none gap-x-4">
                    <dt v-tooltip="'Address'" class="flex-none">
                        <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width
                            aria-hidden="true" />
                    </dt>
                    <dd class="w-full text-gray-500">
                        <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                            <span class="" v-html="data?.customer?.address.formatted_address" />
                            <div v-if="data.address_management.can_open_address_management"
                                @click="() => isModalAddress = true"
                                class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                <span>{{ trans("Edit") }}</span>
                            </div>
                        </div>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</template>
