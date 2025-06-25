<script setup lang="ts">
import BackgroundBox from '@/Components/BackgroundBox.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import Icon from '@/Components/Icon.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'

import { faArrowRight } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import { capitalize, inject } from 'vue'
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import { trans } from 'laravel-vue-i18n'
library.add(faArrowRight)

const props = defineProps<{
    data: {
        customer: {

        }
        channels: {

        }[]
        stats: {

        }[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const ccccxcx = [
    {
        "label": "Departments",
        "route": {
            "name": "retina.dashboard.show",
            "parameters": {
            "organisation": "aw",
            "shop": "awd"
            }
        },
        "icon": "fal fa-folder-tree",
        "color": "#f59e0b",
        "value": 15,
        "metaRight": {
            "tooltip": "Sub Departments",
            "icon": {
            "icon": "fal fa-folder-tree",
            "class": ""
            },
            "count": 0
        },
        "metas": [
            {
                "tooltip": "Active departments",
                "icon": {
                    "tooltip": "active",
                    "icon": "fas fa-check-circle",
                    "class": "text-green-500"
                },
                "count": 15,
                "route": {
                    "name": "retina.dashboard.show",
                    "parameters": {
                    "organisation": "aw",
                    "shop": "awd",
                    "index_elements[state]": "active"
                    }
                }
            },
            {
                "tooltip": "Discontinuing",
                "icon": {
                    "icon": "fas fa-times-circle",
                    "class": "text-amber-500"
                },
                "count": 0,
                "route": {
                    "name": "retina.dashboard.show",
                    "parameters": {
                    "organisation": "aw",
                    "shop": "awd",
                    "index_elements[state]": "discontinuing"
                    }
                }
            },
            {
                "tooltip": "Discontinued Departments",
                "icon": {
                    "icon": "fas fa-times-circle",
                    "class": "text-red-500"
                },
                "count": 0,
                "route": {
                    "name": "retina.dashboard.show",
                    "parameters": {
                    "organisation": "aw",
                    "shop": "awd",
                    "index_elements[state]": "discontinued"
                    }
                }
            },
            {
                "tooltip": "In process",
                "icon": {
                    "icon": "fal fa-seedling",
                    "class": "text-green-500 animate-pulse"
                },
                "count": 0,
                "route": {
                    "name": "retina.dashboard.show",
                    "parameters": {
                    "organisation": "aw",
                    "shop": "awd",
                    "index_elements[state]": "in_process"
                    }
                }
            }
        ]
    }
]
</script>

<template>
    <div class="relative isolate overflow-hidden">
        <!-- <pre>{{ data }}</pre> -->
        <!-- <svg class="absolute inset-0 -z-10 size-full stroke-gray-200 [mask-image:radial-gradient(100%_100%_at_top_right,white,transparent)]"
            aria-hidden="true">
            <defs>
                <pattern id="0787a7c5-978c-4f66-83c7-11c213f99cb7" width="200" height="200" x="50%" y="-1"
                    patternUnits="userSpaceOnUse">
                    <path d="M.5 200V.5H200" fill="none" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" stroke-width="0" fill="url(#0787a7c5-978c-4f66-83c7-11c213f99cb7)" />
        </svg> -->
        
        <div class="mx-auto max-w-7xl px-6 pb-12 pt-10 lg:flex lg:px-14 ">
            <div v-if="data.channels.length" class="mx-auto max-w-2xl lg:mx-0 lg:shrink-0">

                <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                    {{ trans("Your channels summary") }}
                </h1>

                <p class="mt-8 text-pretty text-lg xfont-medium text-gray-500 sm:text-xl/8">
                    {{ trans("Have a look at your channels summary.") }}
                </p>

                <div class="mt-4 grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2">
                    <StatsBox
                        v-for="(stat, idxStat) in data.stats"
                        :stat="stat"
                    />
                </div>
            </div>

            <div v-else class="mx-auto max-w-2xl lg:mx-0 lg:shrink-0 lg:pt-8">
                <!-- <div class="">
                    <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
                    </a>
                </div> -->

                <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                    Manage your orders and products
                </h1>
                <p class="mt-8 text-pretty text-lg font-medium text-gray-500 sm:text-xl/8">
                    Control your orders and products with our easy-to-use dashboard. You can manage your orders, products, and customers all in one place.
                </p>
                <div class="mt-10 flex items-center gap-x-6">
                    <ButtonWithLink
                        :routeTarget="{
                            name: 'retina.dropshipping.customer_sales_channels.create'
                        }"
                        label="Get started"
                        iconRight="far fa-arrow-right"
                    />
                </div>
            </div>
            
        </div>
    </div>
</template>
