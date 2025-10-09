<script setup lang="ts">
import Timeline from '@/Components/Utils/Timeline.vue'
import { ref } from "vue";
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
import { faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck, faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash, faSkull, faDungeon } from '@fal';

library.add(faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck, faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash, faSkull, faDungeon)
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
                recipient_stored_at: any,
                schedule_at: any,
                slug: any,
                ready_at: any,
                sent_at: any,
                cancelled_at: any,
                stopped_at: any,
                date: any,
                created_at: any,
                updated_at: any,
                timeline: any,
                is_layout_blank: any,
                outbox_id: any,
                live_layout: any,
                unpublished_layout: any,
            }
        },
        compiled_layout: any
    }
}>()

const previewOpen = ref(false)
const iframeClass = ref('w-full h-full')
const totalValue = (props.data.mailshot.data.stats.map((item) => item.value || 0)).reduce((acc, val) => acc + val, 0);
const dataSet = {
    labels: (props.data.mailshot.data.stats.map((item) => item.label)),
    datasets: [
        {
            backgroundColor: (props.data.mailshot.data.stats.map((item) => item.color)),
            data: (props.data.mailshot.data.stats.map((item) => item.value || 0)),
        },
    ],
};
</script>



<template>
    <div class="card p-4">
        <div class="col-span-2 w-full pb-4 border-b border-gray-300 mb-8">
            <div class="mt-4 sm:mt-0 pb-2">
                <Timeline :options="data.mailshot.data.timeline" :state="'sent'" :slidesPerView="6" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-8 gap-2">
            <!-- Data Grid -->
            <div class="md:col-span-8 grid sm:grid-cols-1 md:grid-cols-5 gap-2 h-auto mb-3">
                <div v-for="item in data.mailshot.data.stats" :key="item.label" :class="item.class"
                    class="bg-gradient-to-tr flex flex-col justify-between px-6 py-2 rounded-lg shadow-lg sm:h-auto">
                    <div class="flex justify-between items-center mb-2">
                        <div>
                            <div class="text-lg font-semibold">{{ item.label }}</div>
                        </div>
                        <div class="rounded-full bg-white/20 p-2">
                            <FontAwesomeIcon :icon="item.icon" class="text-xl" />
                        </div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold">{{ item.value }}</div>
                        <!--    <div class="text-sm text-white/80">Updated 5 minutes ago</div> -->
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="h-auto mb-3">
                <div class="bg-white  p-4 rounded-lg drop-shadow-2xl overflow-auto relative">
                    <button @click="previewOpen = true"
                        class="absolute top-4 right-2 bg-gray-300 text-white px-2 py-1 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        <FontAwesomeIcon :icon="faExpand" />
                    </button>
                    <div v-if="data.compiled_layout" v-html="data.compiled_layout"></div>
                    <div v-else>
                        <EmptyState :data="{
                            title: 'you dont have any preview'
                        }" />
                    </div>
                </div>
            </div>

            <div class="h-auto mb-3">
                <!-- Conditional Rendering for the Chart -->
                <div
                    class="chart-container bg-white min-h-[28rem] p-4 w-full rounded-lg shadow drop-shadow-2xl relative">
                    <Pie :data="dataSet" :options="{
                        responsive: true,
                        maintainAspectRatio: false,
                        /* plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.raw;
                                        return `${context.label}: ${value} (${value > 1 ? '' : '0'})`;
                                    }
                                }
                            }
                        } */
                    }" />

                    <div v-if="totalValue == 0"
                        class="absolute top-0 left-0 flex justify-center items-center bg-gray-300 rounded-full h-[18rem] w-[18rem]"
                        style="transform: translate(-50%, -50%); top: 64%; left: 50%;">
                        <span class="text-gray-500 text-lg">No Data Available</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Modal :isOpen="previewOpen" @onClose="previewOpen = false">
        <div class="border">
            <div class="bg-gray-300">
                <ScreenView @screenView="(e) => iframeClass = setIframeView(e)" />
            </div>
            <div v-html="data.compiled_layout"></div>
        </div>
    </Modal>
</template>



<style lang="scss" scoped>
.card {
    padding: 1rem;
    border-radius: 8px;

    @media (max-width: 768px) {
        padding: 0.5rem;
    }
}

.grid-cols-7 {
    display: grid;
    grid-template-columns: repeat(7, 1fr);

    @media (max-width: 768px) {
        grid-template-columns: repeat(2, 1fr);
    }
}

.text-xl {
    font-size: 1.25rem;

    @media (max-width: 640px) {
        font-size: 1rem;
    }
}
</style>
