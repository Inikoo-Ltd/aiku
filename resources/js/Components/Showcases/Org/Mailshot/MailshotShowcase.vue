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
import Modal from "@/Components/Utils/Modal.vue"
import { faExpand } from "@fal";
import ScreenView from "@/Components/ScreenView.vue"
import { setIframeView } from "@/Composables/Workshop"
import EmptyState from "@/Components/Utils/EmptyState.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck,
    faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash,
    faSkull, faDungeon
} from '@fal';
import { trans } from 'laravel-vue-i18n';
import { Link } from "@inertiajs/vue3";
import Button from "@/Components/Elements/Buttons/Button.vue"
import TabsBoxDisplay from "@/Components/Dashboards/TabsBoxDisplay.vue"

library.add(
    faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck,
    faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash,
    faSkull, faDungeon
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
        compiled_layout: any
    }
    liveStats?: any[]
}>()

const previewOpen = ref(false)
const iframeClass = ref('w-full h-full')

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
            label: trans('Errors & Rejected Emails'),
            tabs: buildTabs([0, 1]),
        },
        {
            label: trans('Sent & Delivered Emails'),
            tabs: buildTabs([2, 3]),
        },
        {
            label: trans('Hard & Soft Bounced Emails'),
            tabs: buildTabs([4, 5]),
        },
        {
            label: trans('Opened & Clicked Emails'),
            tabs: buildTabs([6, 7]),
        },
        {
            label: trans('Spam & Unsubscribed Emails'),
            tabs: buildTabs([8, 9]),
        },
    ]
})

const mailshotState = computed(() => props.data.mailshot.data.state)

const isInProcess = computed(() => mailshotState.value === "in_process")
const isReady = computed(() => mailshotState.value === "ready")
const isLoadingVisit = ref(false)
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

            <!-- TabsBox stats -->
            <div v-if="!isReady" class="mt-2">
                <TabsBoxDisplay :tabs_box="tabsBox" />
            </div>

            <!-- Preview and chart -->
            <div class="grid gap-4 mt-8" :class="isReady ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2'">
                <div class="h-auto mb-3">
                    <div class="bg-white p-4 rounded-lg shadow relative overflow-auto">
                        <button @click="previewOpen = true"
                            class="absolute top-4 right-3 bg-gray-300 text-white px-2 py-1 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <FontAwesomeIcon :icon="faExpand" />
                        </button>
                        <div v-if="data.compiled_layout" v-html="data.compiled_layout"></div>
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

            <!-- Full preview modal -->
            <Modal :isOpen="previewOpen" @onClose="previewOpen = false">
                <div class="border">
                    <div class="bg-gray-300">
                        <ScreenView @screenView="(e) => iframeClass = setIframeView(e)" />
                    </div>
                    <div v-html="data.compiled_layout"></div>
                </div>
            </Modal>
        </template>
        <div v-if="isInProcess">
            <EmptyState :data="{
                title: trans(`:mailshotSubject is still in process`, { mailshotSubject: props.data.mailshot.data.subject ?? '' }),
            }">
                <template #button-empty-state>
                    <Link :href="route('grp.helpers.redirect_mailshot_workshop', {
                        mailshot: props.data.mailshot.data.id,
                    })
                        " @start="() => (isLoadingVisit = true)" class="mt-4 block w-fit mx-auto">
                        <Button :label="trans('Workshop')" type="secondary" icon="fal fa-drafting-compass"
                            :loading="isLoadingVisit" />
                    </Link>
                </template>
            </EmptyState>
        </div>
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
