<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import { useFormatTime } from "@/Composables/useFormatTime"
import { inject, ref } from "vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import LoadingText from "@/Components/Utils/LoadingText.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Image from "@/Components/Image.vue"
import Icon from "@/Components/Icon.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useTruncate } from "@/Composables/useTruncate"
import { capitalize } from 'lodash-es'
import { Icon as IconTS } from "@/types/Utils/Icon"

const props = defineProps<{
    data?: {
        tooltip?: []
        route?: routeType
        container?: {
            key?: string
            icon: string | string[]
            label: string
            href?: routeType
            tooltip: string
        }
        noCapitalise?: boolean
        icon: IconTS
        state_icon?: IconTS
        image?: any
        model: string
        description: {
            label: string
        }
        code?: {
            label: string
            Tooltip?: string
        }
        meta?: {
            key: string
            type?: string // 'date', 'amount'
            code?: string // 'GBP',
            amount?: number // 30.40, 85
            label?: string | string[]
            number?: number | string
            icon?: IconTS
            leftIcon?: IconTS
            href?: routeType
            tooltip?: string
        }[]
    }
    modelType: string
}>()

const emits = defineEmits<{
    (e: "finishVisit", value: false): void
}>()

const locale = inject("locale", aikuLocaleStructure)

const isLoading = ref(false)
</script>

<template>
    <component :is="data?.route?.name ? Link : 'div'" as="a"
        :href="data?.route?.name ? route(data?.route.name, data?.route.parameters) : ''"
        class="flex gap-x-2 items-center border-b border-gray-300" @start="() => (isLoading = true)" @finish="() => emits('finishVisit', false)">
        <div v-if="isLoading"
            class="fixed inset-0 bottom-0 bg-black/50 flex flex-col justify-center items-center text-white cursor-default">
            <LoadingIcon class="text-6xl" />
            <LoadingText />
        </div>

        <div class="w-full flex leading-none justify-between tracking-tight">
            <div class="">

                <div class="flex py-1 flex-row items-center gap-y-1.5">

                    <h2 xclass="data?.noCapitalise ? '' : 'capitalize'" class="leading-none font-semibold text-base mr-2">
                        <span v-if="data?.model" class="leading-none text-gray-400 mr-2 block sm:inline">
                            {{ data?.model }}
                        </span>
                        <span class="leading-none inline-block whitespace-nowrap ">
                            {{ useTruncate(data?.code?.label || "", 30) }}
                        </span>
                    </h2>

                    <Icon v-if="data?.state_icon" :data="data?.state_icon" size="2xs" class="" />

                    <!-- Section: After Title -->
                    <div class="flex gap-x-2 items-center ml-2">
                        <div v-if="data?.description" class="xfont-semibold text-base leading-none">
                            {{ data?.description.label }}
                        </div>
                    </div>

                    <!-- <div
                        class="border-[2px] border-gray-400 text-gray-500 rounded-md h-10 aspect-square flex items-center justify-center">
                        <Image :src="data?.image" />
                    </div> -->
                </div>

                <!-- Section: mini Tabs -->
                <div v-if="data?.meta?.length" class="flex items-center pr-4 sm:pr-0 flex-wrap sm:gap-y-0.5 text-sm pb-1.5">
                    <Icon v-if="data?.icon" :data="data?.icon" class="text-gray-600 mr-1 text-xs" />

                    <div class="last:hidden pr-1">•</div>

                    <template v-for="meta, index in data?.meta">
                        <div v-tooltip="meta.tooltip" class="flex items-center gap-x-1 text-gray-500 text-xs">
                            <!-- <Icon v-if='meta?.icon' :data="meta.icon" size="sm" /> -->
                            <FontAwesomeIcon v-if="meta?.icon" v-tooltip="meta?.icon?.title ? meta?.icon?.title : capitalize(meta?.icon?.tooltip ?? '')" aria-hidden="true" :icon="meta?.icon?.icon" size="sm" :class="meta?.icon?.class" fixed-width />

                            <template v-if="meta.type === 'date'">
                                {{ useFormatTime(meta.label) }}
                            </template>

                            <template v-else-if="meta.type === 'currency'">
                                {{ meta.label }}
                                {{ locale.currencyFormat(meta.code || "usd", meta.amount) }}
                            </template>

                            <template v-else-if="meta.type === 'location'">
                                <AddressLocation :data="meta.location" />
                            </template>

                            <template v-else-if="meta.type === 'number'">
                                {{ meta.label }} {{ locale.number(meta.number) }}
                                <span v-if="meta.afterLabel">
                                    {{ meta.afterLabel }}
                                </span>
                            </template>

                            <template v-else-if="meta.type === 'address'">
                                <AddressLocation :data="meta.label" />
                            </template>

                            <template v-else>{{ meta.label || '-'}}</template>
                        </div>

                        <div class="last:hidden px-2">•</div>
                    </template>
                </div>
            </div>

            <div v-if="data?.image"
                class="border-[2px] border-gray-400 text-gray-500 rounded-md h-10 aspect-square flex items-center justify-center">
                <Image :src="data?.image" />
            </div>
        </div>
    </component>
</template>
