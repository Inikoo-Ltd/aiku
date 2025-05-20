<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import CountUp from "vue-countup-v3"
import { faArrowRight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
library.add(faArrowRight)


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
    <div class="relative isolate py-12 px-8">
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

        <div class="flex justify-between">
            <h3 class="text-2xl font-semibold">Your stats <span class="text-gray-500 font-normal">({{ platform.name }})</span></h3>
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
</template>