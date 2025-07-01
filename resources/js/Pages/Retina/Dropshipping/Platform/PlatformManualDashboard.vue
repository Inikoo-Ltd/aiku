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
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
library.add(faArrowRight, faCube, faLink, farArrowRight)


const props = defineProps<{
    pageHead: PageHeadingTS
    platformData: {
        orders: {
            label: string
        }
    }
    platform_logo: string,
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

</script>

<template>

    <PageHeading
        :data="pageHead"
    >
        <template #afterTitle>
            <img
                v-tooltip="platform.name"
                :src="platform_logo"
                class="h-8 w-8 xmt-2"
                :alt="platform.name"
            />
        </template>
    </PageHeading>

    <div class="relative isolate py-6 px-8 max-w-6xl">
        <div xv-else>
            <dl class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 max-w-6xl">
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
