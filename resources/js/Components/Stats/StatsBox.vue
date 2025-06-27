<script setup lang="ts">
import { routeType } from '@/types/route'
import { Link } from '@inertiajs/vue3'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject, ref } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Icon from '../Icon.vue'
import { capitalize } from '@/Composables/capitalize'
import BackgroundBox from '../BackgroundBox.vue'
import CountUp from 'vue-countup-v3'

import { faCubes, faSeedling, faRulerCombined } from "@fal"
import { faFireAlt } from "@fad"
import { faCheckCircle, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'
import { StatsBoxTS } from '@/types/Components/StatsBox'

library.add(faCheckCircle, faTimesCircle, faCubes, faSeedling, faRulerCombined, faFireAlt)

const props = defineProps<{
    stat: StatsBoxTS
}>()

const isBoxLoading = ref(false)
const isLoadingMeta = ref<null | number>(null)
const locale = inject('locale', aikuLocaleStructure)
    
</script>

<template>
    <Link
        :href="stat.route?.name ? route(stat.route.name, stat.route.parameters) : ''"
        :style="{
            color: stat.color,
            xbackgroundColor: stat.backgroundColor
        }"
        class="block isolate relative overflow-hidden rounded-lg cursor-pointer border px-4 py-5 shadow-sm sm:p-6 sm:pb-3"
        :class="stat.is_negative ? 'bg-red-100 hover:bg-red-200 border-red-200 hover:border-red-300 text-red-500' : 'bg-white hover:bg-gray-50 border-gray-200'"
        @start="() => isBoxLoading = true"
        @finish="() => isBoxLoading = false"
    >
        <BackgroundBox v-if="!stat.is_negative" class="-z-10 opacity-80 absolute top-0 right-0" />
        <FontAwesomeIcon v-else icon="fad fa-fire-alt" class="text-red-500 -z-10 opacity-40 absolute -bottom-2 -right-5 text-7xl" fixed-width aria-hidden="true" />

        <div class="truncate text-sm font-medium" :class="stat.is_negative ? 'text-red-500' : 'text-gray-400'" xstyle="{ color: stat.is_negative ? stat.color : null }">
            {{ stat.label }}
        </div>

        <dd class="mt-1 text-3xl font-semibold tracking-tight flex gap-x-2 items-center tabular-nums">
            <LoadingIcon v-if="isBoxLoading" class='text-xl' />
            <FontAwesomeIcon v-else-if="typeof stat.icon === 'string'" :icon='stat.icon' class='text-xl' fixed-width aria-hidden='true' />
            <Icon v-else-if="stat.icon" :data="stat.icon" class="text-xl"/>

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
                @start="() => isLoadingMeta = idxMeta"
                @finish="() => isLoadingMeta = null"
                class="group/sub px-2 flex gap-x-1 items-center font-normal"
                :class="meta.route?.name ? 'hover:underline' : ''"
                v-tooltip="capitalize(meta.tooltip) || capitalize(meta.icon?.tooltip)"
            >
                <LoadingIcon v-if="isLoadingMeta == idxMeta" class="md:opacity-50 group-hover/sub:opacity-100" />
                <span v-else-if="meta.logo_icon" v-html="ChannelLogo(meta.logo_icon)" class="flex items-center min-w-6 w-min max-w-10 min-h-4 h-auto max-h-7" />
                <Icon v-else-if="meta.icon" :data="meta.icon" class="" :class="meta.route?.name ? 'md:opacity-50 group-hover/sub:opacity-100' : 'md:opacity-50'" />

                <div class="group-hover/sub:text-gray-700">
                    {{ locale.number(meta.count) }}
                </div>
            </component>
        </div>
        <div v-else class="mt-3">

        </div>
    </Link>
</template>