<script setup lang="ts">
import { inject, ref, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFireAlt } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import BackgroundBox from "../BackgroundBox.vue"
import LoadingIcon from "../Utils/LoadingIcon.vue"
import Icon from "../Icon.vue"
import CountUp from "vue-countup-v3"
import type { StatsBoxTS } from "@/types/Components/StatsBox"

library.add(faFireAlt)

const props = defineProps<{
    stat: StatsBoxTS
    interval: string // <- the selected interval ("all", "1y", "1q", "mtd", etc.)
}>()

const isBoxLoading = ref(false)
const isLoadingMeta = ref<null | number>(null)
const locale = inject("locale", aikuLocaleStructure)

// Get value for the active interval
const activeValue = computed(() => {
    if (typeof props.stat.value === "object")
        return props.stat.value[props.interval] ?? 0
    return props.stat.value ?? 0
})

// Format metas with interval selection
const metasWithInterval = computed(() => {
    return (props.stat.metas || []).map(meta => ({
        ...meta,
        intervalValue:
            typeof meta.count === "object"
                ? meta.count[props.interval] ?? 0
                : meta.count ?? 0
    }))
})
</script>

<template>
    <component
        v-if="activeValue !== 0"
        :is="stat.route?.name ? Link : 'div'"
        :href="stat.route?.name ? route(stat.route.name, stat.route.parameters) : ''"
        :style="{ color: stat.color }"
        class="block isolate relative overflow-hidden rounded-lg border px-4 py-5 shadow-sm sm:p-6 sm:pb-3 bg-gray-50 transition-all"
        :class="[
            stat.is_negative
                ? 'bg-red-100 hover:bg-red-200 border-red-200 hover:border-red-300 text-red-500'
                : 'bg-white hover:bg-gray-50 border-gray-200',
            stat.route?.name ? 'cursor-pointer' : ''
        ]"
        @start="() => (isBoxLoading = true)"
        @finish="() => (isBoxLoading = false)"
    >
        <!-- Background -->
        <slot v-if="stat.color" name="background">
            <BackgroundBox
                v-if="!stat.is_negative"
                class="-z-10 opacity-80 absolute top-0 right-0"
            />
            <FontAwesomeIcon
                v-else
                icon="fad fa-fire-alt"
                class="text-red-500 -z-10 opacity-40 absolute -bottom-2 -right-5 text-7xl"
                fixed-width
                aria-hidden="true"
            />
        </slot>

        <!-- Label -->
        <div
            class="truncate text-sm font-medium"
            :class="stat.is_negative ? 'text-red-500' : 'text-gray-400'"
        >
            {{ stat.label }}
        </div>

        <!-- Value -->
        <dd
            class="mt-1 text-3xl font-semibold tracking-tight flex gap-x-2 items-center tabular-nums"
        >
            <LoadingIcon v-if="isBoxLoading" class="text-xl" />
            <FontAwesomeIcon
                v-else-if="typeof stat.icon === 'string'"
                :icon="stat.icon"
                class="text-xl"
                fixed-width
                aria-hidden="true"
            />
            <Icon v-else-if="stat.icon" :data="stat.icon" class="text-xl" />

            <CountUp
                :endVal="activeValue"
                :duration="1.5"
                :scrollSpyOnce="true"
                :options="{
                    formattingFn: (value: number) => locale.number(value)
                }"
            />
        </dd>

        <!-- Metas -->
        <div
            v-if="metasWithInterval.length"
            class="-ml-2 py-2 text-sm text-gray-500 flex gap-x-3 gap-y-0.5 items-center flex-wrap"
        >
            <component
                v-for="(meta, idxMeta) in metasWithInterval"
                :key="idxMeta"
                :is="meta.route?.name ? Link : 'div'"
                :href="
                    meta.route?.name
                        ? route(meta.route.name, meta.route.parameters)
                        : ''
                "
                class="group/sub px-2 flex gap-x-1 items-center font-normal"
                :class="meta.route?.name ? 'hover:underline' : ''"
                @start="() => (isLoadingMeta = idxMeta)"
                @finish="() => (isLoadingMeta = null)"
                v-tooltip="meta.tooltip || meta.icon?.tooltip"
            >
                <template v-if="!meta?.hide">
                    <LoadingIcon
                        v-if="isLoadingMeta == idxMeta"
                        class="md:opacity-50 group-hover/sub:opacity-100"
                    />
                    <img
                        v-else-if="meta.logo_icon"
                        :src="`/assets/channel_logo/${channel.platform_code}.svg`"
                        class="flex items-center min-w-6 w-min max-w-10 min-h-4 h-auto max-h-7"
                        :alt="channel.platform_name"
                        v-tooltip="channel.platform_name"
                    />
                    <Icon
                        v-else-if="meta.icon"
                        :data="meta.icon"
                        class="md:opacity-50 group-hover/sub:opacity-100"
                    />

                    <div class="group-hover/sub:text-gray-700">
                        {{ locale.number(meta.intervalValue) }}
                    </div>
                </template>
            </component>
        </div>
    </component>
</template>
