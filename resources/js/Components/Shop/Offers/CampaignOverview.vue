<script setup lang='ts'>

import { faDollarSign } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, computed, unref } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'
library.add(faDollarSign)
import Icon from '@/Components/Icon.vue'
import { Link } from '@inertiajs/vue3'
import { Message } from 'primevue'
import { useFormatTime } from '@/Composables/useFormatTime'

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
const injectedCampaign = inject('campaign', null)
const locale = inject('locale', aikuLocaleStructure)

const campaign = computed(() => unref(injectedCampaign))

const toDate = (val?: string | Date | null) => {
    if (!val) return null
    const d = new Date(val)
    return isNaN(d.getTime()) ? null : d
}

const durationDays = computed(() => {
    const start = toDate(campaign.value?.start_at)
    const end = toDate(campaign.value?.end_at)

    if (!start || !end) return '-'

    const diff = end.getTime() - start.getTime()

    if (diff < 0) return 0

    return Math.floor(diff / 86400000) + 1
})

const remainingDays = computed(() => {
    const end = toDate(campaign.value?.end_at)
    if (!end) return '-'

    const now = new Date()

    const diff = end.getTime() - now.getTime()

    if (diff <= 0) return 0

    return Math.ceil(diff / 86400000)
})

const campaignStatus = computed(() => {
    const start = toDate(campaign.value?.start_at)
    const end = toDate(campaign.value?.end_at)
    const now = new Date()

    if (!start || !end) return 'unknown'

    if (now < start) return 'upcoming'
    if (now > end) return 'expired'

    if (remainingDays.value <= 2) return 'ending'

    return 'active'
})

const severity = computed(() => {
    switch (campaignStatus.value) {
        case 'upcoming': return 'info'
        case 'active': return 'warn'
        case 'ending': return 'danger'
        case 'expired': return 'secondary'
        default: return 'info'
    }
})
</script>

<template>
    <div class="bg-white px-2 sm:px-3 md:px-4 pb-4">
        <Message :severity="severity" class="mb-3 mt-3" v-if="campaign">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm w-full">

                <span>
                    <b>Type:</b> {{ campaign.type }}
                </span>

                <span>
                    <b>Start:</b>
                    {{ useFormatTime(campaign.start_at, { formatTime: 'PPP' }) }}
                </span>

                <span>
                    <b>End:</b>
                    {{ useFormatTime(campaign.end_at, { formatTime: 'PPP' }) }}
                </span>

                <span>
                    <b>Duration:</b>
                    {{ durationDays }} days
                </span>

                <span v-if="campaignStatus !== 'expired'" class="font-medium">
                    Ends in {{ remainingDays }} days
                </span>

                <span v-else class="text-gray-500">
                    Campaign expired
                </span>

            </div>
        </Message>
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
