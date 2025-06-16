<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import CountUp from "vue-countup-v3"
import { faArrowRight, faCube, faLink } from "@fal"
import { faArrowRight as farArrowRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"
import Timeline from '@/Components/Utils/Timeline.vue'

import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
library.add(faArrowRight, faCube, faLink, farArrowRight)


const props = defineProps<{
    platformData: {
        orders: {
            label: string
        }
    }
    platform: {
        name: string
        code: string
        icon: string
    }
    customer_sales_channel: {
        reference: string
        slug: string
    }
    timeline: {
        current_state: string
        options: {}[]
    }
    step: {
        label: string
        title: string
        description: string
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const platformImage = {
    manual: 'https://aw.aurora.systems/art/aurora_log_v2_orange.png',
    tiktok: 'https://cdn-icons-png.flaticon.com/64/3046/3046126.png',
    shopify: 'https://cdn-icons-png.flaticon.com/64/5968/5968919.png',
    woocommerce: 'https://e7.pngegg.com/pngimages/490/140/png-clipart-computer-icons-e-commerce-woocommerce-wordpress-social-media-icon-bar-link-purple-violet-thumbnail.png',
}
</script>

<template>
    <div class="relative isolate py-6 px-8 max-w-6xl">
        <!-- Section: Timeline -->
        <div v-if="props.timeline" class="mt-4 mb-8 sm:mt-0 border-b border-gray-200 pb-2">
            <Timeline :options="props.timeline.options" :state="props.timeline.current_state" :slidesPerView="6" />
        </div>

        <!-- <pre>{{ platform }}</pre> -->
        <!-- <pre>{{ platformData }}</pre> -->
        <!-- <div class="hidden sm:absolute sm:-top-10 sm:right-1/2 sm:-z-10 sm:mr-10 sm:block sm:transform-gpu sm:blur-3xl"
            aria-hidden="true">
            <div class="aspect-[1097/845] w-[68.5625rem] bg-gradient-to-tr from-[#ff4694] to-[#776fff] opacity-20"
                style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
        </div>
        <div class="absolute -top-52 left-1/2 -z-10 -translate-x-1/2 transform-gpu blur-3xl sm:top-[-28rem] sm:sm:translate-x-0 sm:transform-gpu"
            aria-hidden="true">
            <div class="aspect-[1097/845] w-[68.5625rem] bg-gradient-to-tr from-[#ff4694] to-[#776fff] opacity-20"
                style="clip-path: polygon(74.1% 44.1%, 100% 61.6%, 97.5% 26.9%, 85.5% 0.1%, 80.7% 2%, 72.5% 32.5%, 60.2% 62.4%, 52.4% 68.1%, 47.5% 58.3%, 45.2% 34.5%, 27.5% 76.7%, 0.1% 64.9%, 17.9% 100%, 27.6% 76.8%, 76.1% 97.7%, 74.1% 44.1%)" />
        </div> -->

        <div v-if="props.timeline">
            <div class="relative isolate">
                <svg class="absolute inset-0 -z-10 size-full stroke-gray-200 [mask-image:radial-gradient(100%_100%_at_top_right,white,transparent)]" aria-hidden="true">
                    <defs>
                        <pattern id="83fd4e5a-9d52-42fc-97b6-718e5d7ee527" width="200" height="200" x="50%" y="-1" patternUnits="userSpaceOnUse">
                            <path d="M100 200V.5M.5 .5H200" fill="none" />
                        </pattern>
                    </defs>
                    <svg x="50%" y="-1" class="overflow-visible fill-gray-50">
                        <path d="M-100.5 0h201v201h-201Z M699.5 0h201v201h-201Z M499.5 400h201v201h-201Z M-300.5 600h201v201h-201Z" stroke-width="0" />
                    </svg>
                    <rect width="100%" height="100%" stroke-width="0" fill="url(#83fd4e5a-9d52-42fc-97b6-718e5d7ee527)" />
                </svg>

                <FontAwesomeIcon :icon="props.step.icon" class="text-[200px] absolute opacity-15 right-12 top-1/2 -translate-y-3/4 -rotate-12" fixed-width aria-hidden="true" />

                <div class="mx-auto max-w-7xl px-6 lg:flex lg:items-center lg:gap-x-10 lg:px-8">
                    <div class="mx-auto max-w-2xl lg:mx-0 lg:flex-auto">
                        <div class="relative flex w-fit items-center gap-x-4 rounded-full bg-gray-100 px-4 py-1 text-sm/6 text-gray-600 ring-1 ring-gray-900/10 hover:ring-gray-900/20">
                            <span class="font-medium text-indigo-600 italic">
                                {{ props.step.label}}
                            </span>
                        </div>
                        <h1 class="mt-4 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                            {{ props.step.title }}
                        </h1>
                        <p class="mt-8 text-pretty text-lg text-gray-500 text-justify">
                            {{ props.step.description }}
                        </p>
                        <div class="mt-10 flex items-center gap-x-6">
                            <ButtonWithLink
                                :routeTarget="props.step?.label?.route_target"
                                :label="props.step?.button?.label"
                                iconRight="far fa-arrow-right"
                                xxbindToLink="{
                                    preserveScroll: true,
                                }"
                            />
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>

        <div v-else>
            <div class="flex justify-between">
                <h3 class="text-2xl font-semibold">{{ platform.name }} <span class="text-gray-500 font-normal">({{ customer_sales_channel.reference }})</span></h3>
                <img
                    v-tooltip="platform.name"
                    :src="platformImage[platform.code]"
                    class="h-8 w-8 mt-2"
                    :alt="platform.name"
                />
            </div>
            <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 max-w-6xl">
                <div v-for="platform in platformData" :key="platform.id" class="relative overflow-hidden rounded-lg ring-1 ring-gray-300 bg-white px-4 pt-5 pb-12 shadow-sm sm:px-6 sm:pt-6">
                    <dt>
                        <div class="absolute rounded-md bg-slate-800 p-3 flex justify-center items-center">
                            <FontAwesomeIcon :icon="platform.icon" class="size-6 text-white" fixed-width aria-hidden="true" />
                        </div>
                        <p class="ml-16 truncate text-sm font-bold">{{ platform.label }}</p>
                    </dt>
                    <dd class="ml-16 flex items-baseline pb-6 sm:pb-7">
                        <p class="text-2xl font-semibold ">
                            <CountUp
                                :endVal="platform.count"
                                :duration="1.5"
                                :scrollSpyOnce="true"
                                :options="{
                                    formattingFn: (value: number) => locale.number(value)
                                }"
                            />
                        </p>
                        <p class="ml-2 flex items-baseline text-sm text-gray-500">
                            {{ platform.description }}
                        </p>
                        <div class="absolute inset-x-0 bottom-0 bg-gray-50 px-4 py-4 sm:px-6 text-sm">
                            <Link :href="route(platform.route.name, platform.route.parameters)" class="font-medium text-slate-600 hover:text-slate-500" >
                                View all<span class="sr-only"> {{ platform.name }} stats</span>
                                <FontAwesomeIcon icon="fal fa-arrow-right" class="ml-1 text-gray-500 text-xs" fixed-width aria-hidden="true" />
                            </Link >
                        </div>
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</template>