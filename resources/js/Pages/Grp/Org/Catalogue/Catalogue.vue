<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 13 Oct 2022 15:35:22 Central European Summer Plane Malaga - East Midlands UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCubes, faSeedling } from "@fal"
import { faCheckCircle, faTimesCircle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { inject, ref } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import CountUp from 'vue-countup-v3'
import BackgroundBox from '@/Components/BackgroundBox.vue'
import Icon from '@/Components/Icon.vue'
import { trans } from 'laravel-vue-i18n'
import Image from '@/Components/Image.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'
import { Image as ImageProxy } from "@/types/Image"

library.add(faCheckCircle, faTimesCircle, faCubes, faSeedling)

const props = defineProps<{
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    },
    title: string
    stats: {
        id: number
        label: string
        value: number
        change: number
        changeType: string
        icon: string
        color: string
        route: {
            name: string
            parameters: {}
        }
        metaRight?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }
        metas?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }[]
    }[]
    top_selling: {
        product: {
            value: {
                id: number
                name: string
                code: string
                images: {
                    data: {
                        source: ImageProxy
                    }[]
                }
                sold_on_month: number
                stock: number
                price: number
            }
        }
        family: {
            value: {
                id: number
                name: string
            }
            icon: string
        }
        department: {
            value: {
                id: number
                name: string
                current_families: number
                current_products: number
            }
        }
    }
}>()


const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

// const stats = [
//     { id: 1, label: 'Total Subscribers', stat: '71,897', change: '122', changeType: 'increase' },
//     { id: 2, label: 'Avg. Open Rate', stat: '58.16%', change: '5.4%', changeType: 'increase' },
//     { id: 3, label: 'Avg. Click Rate', stat: '24.57%', change: '3.2%', changeType: 'decrease' },
// ]


const boxLoaded = ref<{[key: string]: boolean}>({})
const isLoadingMeta = ref<string | null>(null)
console.log('props', props)
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- Stats: box -->
    <div class="p-6">
        <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="(stat, idxStat) in stats"
                :key="'stat' + idxStat"
                :href="route(stat.route.name, stat.route.parameters)"
                :style="{color: stat.color}"
                class="isolate relative overflow-hidden rounded-lg bg-white hover:bg-gray-50 cursor-pointer border border-gray-200 px-4 py-5 shadow-sm sm:p-6 sm:pb-3"
                @start="() => boxLoaded[idxStat] = true"
                @finish="() => boxLoaded[idxStat] = false"
            >
                <BackgroundBox class="-z-10 opacity-80 absolute top-0 right-0" />

                <dt class="truncate text-sm font-medium text-gray-400">
                    {{ stat.label }}
                </dt>

                <dd class="mt-1 text-3xl font-semibold tracking-tight flex gap-x-2 items-center tabular-nums">
                    <LoadingIcon v-if="boxLoaded[idxStat]" class='text-xl' />
                    <FontAwesomeIcon v-else :icon='stat.icon' class='text-xl' fixed-width aria-hidden='true' />
                    <CountUp
                        :endVal='stat?.value'
                        :duration='1.5'
                        :scrollSpyOnce='true'
                        :options='{
                            formattingFn: (value: number) => locale.number(value)
                        }'
                    />
                </dd>

                <!-- Meta right -->
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

                <!-- Meta -->
                <div v-if="stat.metas?.length" class="-ml-2 py-2 text-sm text-gray-500 flex gap-x-3 gap-y-0.5 items-center flex-wrap">
                    <component
                        v-for="(meta, idxMeta) in stat.metas"
                        :is="meta.route?.name ? Link : 'div'"
                        :href="meta.route?.name ? route(meta.route.name, meta.route.parameters) : ''"
                        @start="() => isLoadingMeta = idxMeta + '-' + idxStat"
                        @finish="() => isLoadingMeta = null"
                        class="group/sub px-2 flex gap-x-0.5 items-center font-normal"
                        :class="meta.route?.name ? 'hover:underline' : ''"
                        v-tooltip="capitalize(meta.tooltip) || capitalize(meta.icon?.tooltip)"
                    >
                        <LoadingIcon v-if="isLoadingMeta == idxMeta + '-' + idxStat" class="md:opacity-50 group-hover/sub:opacity-100" />
                        <Icon v-else :data="meta.icon" class="" :class="meta.route?.name ? 'md:opacity-50 group-hover/sub:opacity-100' : 'md:opacity-50'" />
                        <div class="group-hover/sub:text-gray-700">
                            {{ locale.number(meta.count) }}
                        </div>
                    </component>
                </div>
            </Link>
        </dl>
    </div>
    
    <!-- Section: Top of the Month -->
    <div v-if="top_selling?.product?.value?.all || top_selling?.department?.value?.all || top_selling?.family?.value?.all" class="p-6">
        <div class="text-xl font-semibold py-1 border-b border-gray-200">{{ trans("Top of the month (Top_selling)") }}</div>
        <dl class="isolate mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 sm:grid-rows-2 h-72">
            <!-- Top_selling: Product -->
            <div v-if="top_selling.product.value" class="row-span-2 example-2 rounded-md">
                <div class="inner group bg-gray-100 h-full rounded-md px-8 py-8 flex gap-x-4"
                    :style="{
                        background: `color-mix(in srgb, ${layout?.app?.theme[0]} 10%, white)`
                    }"
                >
                    <div class="aspect-square h-1/2 lg:h-full w-fit flex-shrink-0 rounded-md overflow-hidden">
                        <!-- <img src="https://www.ancientwisdom.biz/wi.php?id=1857494&s=705x705" class="h-full w-auto z-10" /> -->
                        <Image :src="top_selling.product.value?.images?.data?.[0]?.source" />
                    </div>

                    <div class="flex flex-col justify-between gap-y-1">
                        <div>
                            <div class="text-indigo-600 text-sm animate-pulse">{{ trans("Product of the month") }}</div>
                            <h3 class="text-xl font-semibold">
                                {{ top_selling.product.value?.name }}
                            </h3>
                            <div class="text-gray-400 text-sm">{{ top_selling.product.value?.code || '-' }}</div>
                        </div>
                        <div>
                            <p aria-hidden="true" class="text-gray-500">{{ trans('Sold this month') }}: {{ top_selling.product.value?.sold_on_month || '-' }}</p>
                            <p aria-hidden="true" class="text-gray-500">{{ trans('Stock') }}: {{ top_selling.product.value?.stock || '-' }}</p>
                            <p aria-hidden="true" class="text-gray-500">{{ trans('Price') }}: {{ top_selling.product.value?.price || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top_selling: Department -->
            <div v-if="top_selling.department.value" class="bg-gray-50 border-gray-200 border rounded-md flex items-center p-6">
                <div class="flex gap-x-2 items-center">
                    <div class="p-3 rounded">
                        <FontAwesomeIcon icon='fal fa-folder-tree' class='text-indigo-500 text-xl' v-tooltip="trans('Department')" fixed-width aria-hidden='true' />
                    </div>
                    <div class="">
                        <div class="text-xl font-medium">{{ top_selling.department.value.name }}</div>
                        <div class="flex gap-x-10">
                            <div class="text-gray-500">
                                <FontAwesomeIcon icon='fal fa-folder' class='text-gray-400' fixed-width aria-hidden='true' />
                                {{ top_selling.department.value.current_families }}
                            </div>
                            <div class="text-gray-500">
                                <FontAwesomeIcon icon='fal fa-cube' class='text-gray-400' fixed-width aria-hidden='true' />
                                {{ top_selling.department.value.current_products }}
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- Top_selling: Family -->
            <div v-if="top_selling.family.value" class="bg-gray-50 border-gray-200 border rounded-md flex items-center p-6">
                <div class="flex gap-x-2 items-center">
                    <div class="p-3 rounded">
                        <FontAwesomeIcon :icon='top_selling.family.icon' class='text-indigo-500 text-xl' v-tooltip="trans('Family')" fixed-width aria-hidden='true' />
                    </div>

                    <div class="">
                        <div class="text-xl font-medium">{{ top_selling.family.value.name }}</div>
                        <!-- <div class="flex gap-x-10">
                            <div class="text-gray-500">
                                <FontAwesomeIcon icon='fal fa-folder' class='text-gray-400' fixed-width aria-hidden='true' />
                                {{ top_selling.department.value.current_families }}
                            </div>
                            <div class="text-gray-500">
                                <FontAwesomeIcon icon='fal fa-cube' class='text-gray-400' fixed-width aria-hidden='true' />
                                {{ top_selling.department.value.current_products }}
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>

        </dl>
    </div>

    <!-- <pre>{{ top_selling }}</pre> -->


</template>

<style lang="scss">
.example-2 {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1px;
}

.example-2 .inner {
  position: relative;
  z-index: 1;
  width: 100%;
}
.example-2 .inner {
  margin: 0;
}
.example-2::before {
  content: "";
  display: block;
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 100%, transparent)`') 50%,
    rgba(255, 255, 255, 0) 100%
  );
  height: 150%;
  width: 300px;
  transform: translate(0);
  position: absolute;
  animation: rotate 3s linear forwards infinite;
  z-index: 0;
  top: 50%;
  transform-origin: top center;
}

@keyframes rotate {
    from {
        transform: rotate(0);
    }

    to {
        transform: rotate(360deg);
    }
}

</style>