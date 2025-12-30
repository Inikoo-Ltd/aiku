<script setup lang='ts'>

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'
import Coupon from '@/Components/Utils/Coupon.vue'
library.add(faDollarSign)

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
    <div>
        <div class="flex gap-x-3 gap-y-4 p-4 flex-wrap">
            <div v-for="stat in data.stats" class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
                <div class="flex justify-between items-center mb-1">
                    <div class="">{{ stat.label }}</div>
                    <FontAwesomeIcon :icon='stat.icon' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
                </div>
                <div class="mb-1 text-2xl font-semibold">
                    <CountUp
                        :endVal="stat.value"
                        :duration="1.5"
                        :scrollSpyOnce="true"
                        :options="{
                            formattingFn: (value: number) => locale.number(value)
                        }"
                    />
                </div>
                <!-- <div class="text-sm text-gray-400">{{ stat.meta.value }} {{ stat.meta.label }}</div> -->
            </div>
        </div>

        <div>
            <Coupon :first_order_bonus="data?.offers" :currency_code="data.currency_code" />
        </div>
    </div>
</template>