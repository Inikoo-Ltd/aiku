<script setup lang='ts'>

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'
library.add(faDollarSign)
import Icon from '@/Components/Icon.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
    data: {
        stats: {
            label: string
            icon: string
            value: number
            meta: {
                value: number
                label: string
            }
            route_target?: string
        }[]
        offerCampaign: {

        }
        offers: {

        }[]
        currency_code: string
    }
}>()
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="bg-white px-2 sm:px-3 md:px-4 pb-4">

        <div class="flex flex-wrap gap-2 md:gap-4 w-full pt-3">

            <div v-for="stat in data.stats" :key="stat.label"
                class="basis-0 min-w-[110px] sm:min-w-[130px] md:min-w-[160px] flex flex-col items-center rounded-lg md:rounded-xl border border-gray-200 py-3 px-2"
                :style="{ flexGrow: 1 }">

                <!-- LABEL -->
                <div class="text-xs md:text-sm text-gray-500 font-medium text-center mb-2">
                    {{ stat.label }}
                </div>

                <!-- ICON -->
                <div class="mb-2">
                    <Icon v-if="stat.icon" :data="{ icon: stat.icon }" :title="stat.label"
                        class="text-gray-400 text-lg md:text-xl" />
                </div>

                <!-- BORDER SEPARATOR -->
                <div class="w-full border-t border-gray-200 my-2"></div>

                <!-- VALUE -->
                <div class="text-lg md:text-2xl font-semibold text-gray-900">

                    <Link v-if="stat.route_target" :href="stat.route_target"
                        class="flex flex-col items-center hover:underline">
                        <CountUp :endVal="stat.value" :duration="1.5" :scrollSpyOnce="true" :options="{
                            formattingFn: (value: number) => locale.number(value)
                        }" />
                    </Link>
                    <div v-else>
                        <CountUp :endVal="stat.value" :duration="1.5" />
                    </div>
                </div>
            </div>

        </div>

    </div>
</template>
