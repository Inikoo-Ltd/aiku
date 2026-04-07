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
import { faPlus, faChevronDown, faTimes, faPencil, faSparkles } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { routeType } from '@/types/route'
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"

library.add(faPlus, faChevronDown, faTimes, faPencil)

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
        tabsBox?: {
            label: string
            value: string | number
            indicator?: boolean
            tab_slug: string
            type?: string // 'icon', 'date', 'number', 'currency'
            align?: string
            icon?: string | string[]
            iconClass?: string
            tooltip?: string
            information?: {
                label: string | number
                type?: string // 'icon', 'date', 'number', 'currency'
            }
            visitRoute?: {
                name: string
                parameters: {}
            }
        }
        currency_code: string
        amnesty_offer: string
        edit_amnesty_route: routeType
        show_amnesty_route: routeType
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const campaign = computed(() => unref(props.data?.amnesty_offer))

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
        <Message :severity="severity"
            class="mb-3 mt-3 [&_.p-message-text]:w-full [&_.p-message-text]:flex !bg-black !text-yellow-300"
            v-if="campaign">
            <!-- !bg-black !text-yellow-300 -->
            <div class="!w-full flex flex-col md:flex-row md:items-center gap-2">

                <!-- LEFT -->
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm flex-1">

                    <span>
                        <FontAwesomeIcon icon="fal fa-candle-holder" class="" aria-hidden="true" />
                        Gold Reward Amnesty
                    </span>
                    <div class="flex items-center gap-2">
                        <span>
                            {{ useFormatTime(campaign.start_at, { formatTime: 'PPP' }) }}
                        </span>

                        <FontAwesomeIcon icon="fas fa-arrow-right" class="" aria-hidden="true" />

                        <span>
                            {{ useFormatTime(campaign.end_at, { formatTime: 'PPP' }) }}
                        </span>
                    </div>

                    <span>
                        <b>Duration:</b>
                        {{ durationDays }} days
                    </span>

                    <span v-if="campaignStatus !== 'expired'" class="font-medium">
                        Ends in {{ remainingDays }} days
                    </span>

                    <span v-else class="text-yellow-400">
                        Campaign expired
                    </span>

                </div>

                <!-- RIGHT -->
                <div class="flex gap-2 ml-auto">


                    <ButtonWithLink type="yellow" label="Show" size="md" :bindToLink="{ preserveScroll: true }"
                        :routeTarget="data.show_amnesty_route" />
                    <ButtonWithLink type="yellow" label="Edit" size="md" :bindToLink="{ preserveScroll: true }"
                        :routeTarget="data.edit_amnesty_route" />

                </div>
            </div>
        </Message>
        <TabsBoxDisplay v-if="data.tabsBox" :tabs_box="data.tabsBox" />
        <div class="flex flex-wrap gap-2 md:gap-4 w-full pt-3">
            <div v-for="stat in data.stats" :key="stat.label"
                class="basis-0 min-w-[110px] sm:min-w-[130px] md:min-w-[160px] flex flex-col items-center rounded-lg md:rounded-xl border border-gray-200 py-3 px-2"
                :style="{ flexGrow: 1 }">

                <!-- LABEL -->
                <div class="text-xs md:text-sm text-black font-medium text-center mb-2">
                    {{ stat.label }}
                </div>

                <!-- ICON -->
                <div class="mb-2">
                    <Link v-if="stat.route_target" :href="stat.route_target"
                        class="flex justify-center gap-2 hover:underline">
                        <Icon v-if="stat.icon" :data="{ icon: stat.icon }" :title="stat.label" class="text-gray-400 text-lg md:text-xl" />
                        <CountUp :endVal="stat.value" :duration="1.5" :scrollSpyOnce="true" :options="{
                            formattingFn: (value: number) => locale.number(value)
                        }" />
                    </Link>
                    <div v-else>
                        <Icon v-if="stat.icon" :data="{ icon: stat.icon }" :title="stat.label" class="text-gray-400 text-lg md:text-xl" />
                        <CountUp :endVal="stat.value" :duration="1.5" />
                    </div>
                </div>

                <!-- BORDER SEPARATOR -->
                <div class="w-full border-t border-gray-200 my-2"></div>

                <!-- VALUE -->
                <div class="text-lg md:text-2xl font-semibold text-gray-900">

                    
                </div>
            </div>

        </div>

    </div>
</template>
<style scoped>
.p-message .p-message-text {
    width: 100%;
    display: flex;
}
</style>