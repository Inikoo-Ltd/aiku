<script setup lang="ts">
import Timeline from '@/Components/Utils/Timeline.vue'
import { ref, computed } from "vue";
import { Pie } from "vue-chartjs";
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement,
} from "chart.js";
import Dialog from "primevue/dialog"
import { faExpand, faDesktop, faMobile } from "@fal";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck,
    faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash,
    faSkull, faDungeon, faExclamationTriangle
} from '@fal';
import { ctrans } from '@/Composables/useTrans'
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"
import MailshotGettingStarted from "./MailshotGettingStarted.vue"
import { routeType } from "@/types/route";

library.add(
    faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck,
    faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash,
    faSkull, faDungeon, faExclamationTriangle, faDesktop, faMobile
);
ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps<{
    data: {
        mailshot: {
            data: {
                id: any,
                subject: any,
                state: any,
                state_label: any,
                state_icon: any,
                stats: any,
                timeline: any,
            }
        },
        compiled_layout: any,
        compiled_layout_size: number
        is_composed?: boolean
    }
    liveStats?: any[]
    ownShopTemplates?: Array<{
        id: number,
        slug: string,
        name: string,
        compiled_layout: string,
        created_at: string,
        shop_name: string
    }>
    otherShopTemplates?: Array<{
        id: number,
        slug: string,
        name: string,
        compiled_layout: string,
        created_at: string,
        shop_name: string
    }>
    workshopRoute?: routeType
}>()

const previewOpen = ref(false)
const previewDevice = ref<'desktop' | 'mobile'>('desktop')

const stats = computed(
    () => props.liveStats && props.liveStats.length
        ? props.liveStats
        : props.data.mailshot.data.stats
)

const totalValue = computed(() =>
    stats.value.map((item: any) => item.value || 0).reduce((acc: number, val: number) => acc + val, 0)
)

const mailshotColors = [
    "#22c55e",
    "#a3e635",
    "#38bdf8",
    "#fb7185",
    "#fbbf24",
    "#4f46e5",
    "#ec4899",
    "#14b8a6",
    "#f97316",
    "#6b7280",
]

const dataSet = computed(() => ({
    labels: stats.value.map((item: any) => item.label),
    datasets: [
        {
            data: stats.value.map((item: any) => item.value || 0),
            backgroundColor: stats.value.map((_: any, index: number) =>
                mailshotColors[index % mailshotColors.length]
            ),
            hoverOffset: 4,
        },
    ],
}))

const pieOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                usePointStyle: true,
                pointStyle: "circle",
            },
        },
    },
}

const tabsBox = computed(() => {
    const s = stats.value || []

    const getStat = (index: number) => s[index] || { label: '', value: 0, key: `stat_${index}`, icon: null }

    const buildTabs = (indices: number[]) =>
        indices.map((i) => {
            const stat = getStat(i)
            return {
                tab_slug: stat.key,
                label: stat.label,
                value: stat.value ?? 0,
                type: 'number',
                icon: stat.icon,
                icon_data: {
                    icon: stat.icon,
                    tooltip: stat.label,
                },
            }
        })

    return [
        {
            label: ctrans('Errors & Rejected Emails'),
            tabs: buildTabs([0, 1]),
        },
        {
            label: ctrans('Sent & Delivered Emails'),
            tabs: buildTabs([2, 3]),
        },
        {
            label: ctrans('Hard & Soft Bounced Emails'),
            tabs: buildTabs([4, 5]),
        },
        {
            label: ctrans('Opened & Clicked Emails'),
            tabs: buildTabs([6, 7]),
        },
        {
            label: ctrans('Spam & Unsubscribed Emails'),
            tabs: buildTabs([8, 9]),
        },
    ]
})

const mailshotState = computed(() => props.data.mailshot.data.state)

const isInProcess = computed(() => mailshotState.value === "in_process")
const isReady = computed(() => mailshotState.value === "ready")

</script>

<template>
    <div class="card p-4">
        <template v-if="!isInProcess">
            <div class="col-span-2 w-full pb-4 border-gray-300" v-if="!isReady">
                <div class="mt-4 sm:mt-0 pb-2">
                    <Timeline :options="data.mailshot.data.timeline" :state="data.mailshot.data.state"
                        :slidesPerView="6" />
                </div>
            </div>
            <div v-if="isReady" class="mb-4">
                <div v-if="data.compiled_layout_size > 102"
                    class="flex items-start gap-3 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-md shadow-sm">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500 text-2xl mt-0.5 flex-shrink-0"
                        fixed-width />
                    <div class="flex-1">
                        <h4 class="text-yellow-700 font-semibold text-base mb-1">
                            Email size exceeds Gmail's recommended limit
                        </h4>
                        <p class="text-yellow-600 text-sm leading-relaxed">
                            Your email content is <span class="font-semibold">{{ data.compiled_layout_size }} KB</span>,
                            which exceeds the recommended <span class="font-semibold">102 KB</span> limit.
                            Gmail may clip your message and hide part of the content behind a
                            "[Message clipped] View entire message" link, which can hurt engagement
                            and tracking accuracy.
                        </p>
                    </div>
                </div>
            </div>

            <!-- TabsBox stats -->
            <div v-if="!isReady" class="mt-2">
                <TabsBoxDisplay :tabs_box="tabsBox" />
            </div>

            <!-- Preview and chart -->
            <div class="grid gap-4 mt-8" :class="isReady ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2'">
                <div class="h-auto mb-3">
                    <div class="bg-white p-4 rounded-lg shadow relative overflow-auto">
                        <button v-if="data.compiled_layout" type="button" @click="previewOpen = true" v-tooltip="ctrans('Full preview')"
                            class="absolute right-3 top-3 z-10 rounded-md border border-gray-200 bg-white px-2 py-1 text-gray-600 shadow-sm hover:text-[var(--theme-color-4)]">
                            <FontAwesomeIcon :icon="faExpand" fixed-width aria-hidden="true" />
                        </button>
                        <iframe v-if="data.compiled_layout" :srcdoc="data.compiled_layout" sandbox="allow-popups" :title="ctrans('Email preview')"
                            class="h-[640px] w-full border-0 bg-white" />
                        <EmptyState v-else :data="{ title: 'You don’t have any preview' }" />
                    </div>
                </div>

                <div class="h-auto mb-3">
                    <div v-if="!isReady"
                        class="bg-white p-4 rounded-lg shadow relative min-h-[28rem] flex justify-center items-center">
                        <Pie :data="dataSet" :options="pieOptions" />
                        <div v-if="totalValue == 0"
                            class="absolute inset-0 flex justify-center items-center bg-gray-100 rounded-lg">
                            <span class="text-gray-500 text-lg">No Data Available</span>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog v-model:visible="previewOpen" modal dismissableMask :draggable="false" :header="data.mailshot.data.subject"
                :style="{ width: '64rem' }" :breakpoints="{ '1100px': '95vw' }"
                :pt="{ header: { class: '!px-5 !py-3 border-b border-gray-200' }, content: { class: '!p-0' } }">
                <div class="flex items-center justify-center border-b border-gray-200 bg-white py-2">
                    <div class="flex items-center rounded-md bg-gray-100 p-0.5">
                        <button type="button" class="flex h-7 items-center gap-x-1.5 rounded px-3 text-xs"
                            :class="previewDevice === 'desktop' ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="previewDevice = 'desktop'">
                            <FontAwesomeIcon :icon="faDesktop" fixed-width aria-hidden="true" />
                            {{ ctrans('Desktop') }}
                        </button>
                        <button type="button" class="flex h-7 items-center gap-x-1.5 rounded px-3 text-xs"
                            :class="previewDevice === 'mobile' ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                            @click="previewDevice = 'mobile'">
                            <FontAwesomeIcon :icon="faMobile" fixed-width aria-hidden="true" />
                            {{ ctrans('Mobile') }}
                        </button>
                    </div>
                </div>
                <div class="flex h-[75vh] justify-center bg-gray-100 p-4">
                    <iframe :srcdoc="data.compiled_layout" sandbox="allow-popups" :title="ctrans('Email preview')"
                        class="h-full border-0 bg-white shadow-sm transition-all"
                        :class="previewDevice === 'mobile' ? 'w-[395px] rounded-[24px] border-[10px] border-gray-800' : 'w-full'" />
                </div>
            </Dialog>
        </template>
        <MailshotGettingStarted v-if="isInProcess" :subject="data.mailshot.data.subject" :isComposed="!!data.is_composed"
            :workshopRoute="workshopRoute" :ownShopTemplates="ownShopTemplates" :otherShopTemplates="otherShopTemplates" />
    </div>
</template>

<style lang="scss" scoped>
.card {
    border-radius: 8px;
    padding: 1rem;

    @media (max-width: 768px) {
        padding: 0.5rem;
    }
}
</style>
