<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from "@/Stores/locale"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faWifi, faCheckCircle, faGasPump, faFlagUsa } from '@fas'
import { faEye } from '@far'
import { faHistory } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faEye, faWifi, faHistory, faCheckCircle, faGasPump, faFlagUsa)

const props = defineProps<{
    stat: {
        label: string
        count: number
        sticker: {
            icon: string
            class: string
        }
        cases: {
            [key: string]: {
                label: string
                count: number
                icon: {
                    icon: string
                    class: string
                    tooltip: string
                }
            }
        }
    }
}>()



</script>

<template>
    <div class="relative px-5 py-4 rounded-lg bg-white border border-indigo-100 shadow tabular-nums min-w-72">
        <div class="absolute right-4 h-10 aspect-square rounded-md text-white flex justify-center items-center" :class='stat.sticker.class'>
            <FontAwesomeIcon :icon='stat.sticker.icon' fixed-width aria-hidden='true' />
        </div>
        <dt class="text-base font-medium text-gray-400">{{ stat.label }}</dt>
        <dd class="mt-2 flex justify-between gap-x-2">
            <div class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
                <!-- In Total -->
                <div class="flex gap-x-2 items-end">
                    {{ useLocaleStore().number(stat.count) }}
                    <span class="text-sm font-medium leading-4 text-gray-500 ">{{ trans('in total') }}</span>
                </div>

                <!-- Statistic -->
                <div class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
                    <div v-for="dCase in stat.cases" class="flex gap-x-0.5 items-center font-normal"
                        v-tooltip="dCase.icon.tooltip">
                        <FontAwesomeIcon :icon='dCase.icon.icon' :class='dCase.icon.class' fixed-width aria-hidden='true' />
                        <span class="font-semibold">
                            {{ useLocaleStore().number(dCase.count) }}
                        </span>
                    </div>
                </div>
            </div>

        </dd>
    </div>
</template>