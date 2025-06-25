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
                <!-- <div class="">
                    <a href="#" class="inline-flex space-x-6">
                        <span class="rounded-full bg-indigo-600/10 px-3 py-1 text-sm/6 font-semibold text-indigo-600 ring-1 ring-inset ring-indigo-600/10">
                            What's new?
                        </span>
                    </a>
                </div> -->

                <h1 class="mt-10 text-pretty text-5xl font-semibold tracking-tight sm:text-7xl">
                    Your channels summary
                </h1>

                <p class="mt-8 text-pretty text-lg xfont-medium text-gray-500 sm:text-xl/8">
                    Have a look at your channels summary.
                </p>

                <div class="mt-4 grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2">
                    <Link
                        v-for="(stat, idxStat) in data.stats"
                        :key="'stat' + idxStat"
                        :href="route(stat.route.name, stat.route.parameters)"
                        :style="{
                            color: stat.color,
                            xbackgroundColor: stat.backgroundColor
                        }"
                        class="isolate relative overflow-hidden rounded-lg cursor-pointer border px-4 py-5 shadow-sm sm:p-6 sm:pb-3"
                        xclass="stat.is_negative ? 'bg-red-100 hover:bg-red-200 border-red-200 hover:border-red-300 text-red-500' : 'bg-white hover:bg-gray-50 border-gray-200'"
                        xstart="() => boxLoaded[idxStat] = true"
                        xfinish="() => boxLoaded[idxStat] = false"
                    >
                        <BackgroundBox v-if="!stat.is_negative" class="-z-10 opacity-80 absolute top-0 right-0" />
                        <FontAwesomeIcon v-else icon="fad fa-fire-alt" class="text-red-500 -z-10 opacity-40 absolute -bottom-2 -right-5 text-7xl" fixed-width aria-hidden="true" />
                        <dt class="truncate text-sm font-medium" :class="stat.is_negative ? 'text-red-500' : 'text-gray-400'" xstyle="{ color: stat.is_negative ? stat.color : null }">
                            {{ stat.label }}
                        </dt>
                        <dd class="mt-1 text-3xl font-semibold tracking-tight flex gap-x-2 items-center tabular-nums">
                            <LoadingIcon v-if="!'boxLoaded[idxStat]'" class='text-xl' />
                            <FontAwesomeIcon v-else-if="icon" :icon='stat.icon' class='text-xl' fixed-width aria-hidden='true' />
                            <CountUp
                                :endVal='stat?.value'
                                :duration='1.5'
                                :scrollSpyOnce='true'
                                :options='{
                                    formattingFn: (value: number) => locale.number(value)
                                }'
                            />
                        </dd>
                        
                        <component
                            v-if="stat.metaRight"
                            :is="stat.metaRight?.route?.name ? Link : 'div'"
                            :href="stat.metaRight?.route?.name ? route(stat.metaRight?.route.name, stat.metaRight?.route.parameters) : ''"
                            class="text-base rounded group/mr absolute top-6 right-5 px-2 flex gap-x-0.5 items-center font-normal"
                            :style="{
                                background: `color-mix(in srgb, white 90%, ${stat.color})`,
                                border: `1px solid ${stat.color}`,
                                color: `color-mix(in srgb, black 20%, ${stat.color})`
                            }"
                            v-tooltip="capitalize(stat.metaRight?.tooltip) || capitalize(stat.metaRight?.icon?.tooltip)"
                        >
                            <Icon :data="stat.metaRight?.icon" class="opacity-100"/>
                            <div class="group-hover/sub:text-gray-700">
                                {{ locale.number(stat.metaRight?.count) }}
                            </div>
                        </component>
                        
                        <div v-if="stat.metas?.length" class="-ml-2 py-2 text-sm text-gray-500 flex gap-x-3 gap-y-0.5 items-center flex-wrap">
                            <component
                                v-for="(meta, idxMeta) in stat.metas"
                                :is="!'meta.route?.name' ? Link : 'div'"
                                xhref="meta.route?.name ? route(meta.route.name, meta.route.parameters) : ''"
                                xstart="() => isLoadingMeta = idxMeta + '-' + idxStat"
                                xfinish="() => isLoadingMeta = null"
                                class="group/sub px-2 flex gap-x-0.5 items-center font-normal"
                                :class="meta.route?.name ? 'hover:underline' : ''"
                                v-tooltip="capitalize(meta.tooltip) || capitalize(meta.icon?.tooltip)"
                            >
                            <!-- {{ meta.logo_icon }} -->
                                <LoadingIcon v-if="'isLoadingMeta' == idxMeta + '-' + idxStat" class="md:opacity-50 group-hover/sub:opacity-100" />
                                <Icon v-else-if="!meta.icon" :data="meta.icon" class="" :class="meta.route?.name ? 'md:opacity-50 group-hover/sub:opacity-100' : 'md:opacity-50'" />
                                <span v-else v-html="ChannelLogo(meta.logo_icon)" class="flex items-center min-w-6 w-min max-w-10 min-h-4 h-auto max-h-7">

                                </span>
                                <div class="group-hover/sub:text-gray-700">
                                    {{ locale.number(meta.count) }}
                                </div>
                            </component>
                        </div>
                    </Link>
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
