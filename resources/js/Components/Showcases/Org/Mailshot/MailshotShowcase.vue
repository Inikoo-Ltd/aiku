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
import {
  faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck,
  faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash,
  faSkull, faDungeon
} from '@fal';

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
}>()

const previewOpen = ref(false)
const iframeClass = ref('w-full h-full')

const totalValue = (props.data.mailshot.data.stats.map((item) => item.value || 0))
  .reduce((acc, val) => acc + val, 0);

const dataSet = {
  labels: props.data.mailshot.data.stats.map((item) => item.label),
  datasets: [
    {
      backgroundColor: props.data.mailshot.data.stats.map((item) => item.color),
      data: props.data.mailshot.data.stats.map((item) => item.value || 0),
    },
  ],
};
</script>

<template>
  <div class="card p-4">
    <div class="col-span-2 w-full pb-4 border-gray-300">
      <div class="mt-4 sm:mt-0 pb-2">
        <Timeline :options="data.mailshot.data.timeline" :state="'sent'" :slidesPerView="6" />
      </div>
    </div>

    <!-- Horizontal compact stats -->
<div class="rounded-md bg-white border border-gray-200 overflow-hidden">
  <div class="grid grid-cols-5 divide-x divide-y divide-gray-200">
    <div
      v-for="item in data.mailshot.data.stats"
      :key="item.label"
      class="px-3 py-4 flex flex-col items-center justify-center hover:bg-gray-50 transition"
    >
      <div class="flex items-center justify-center gap-2 text-gray-700">
        <FontAwesomeIcon :icon="item.icon" class="text-base" />
        <span class="text-sm font-medium truncate">{{ item.label }}</span>
      </div>

      <div class="flex items-baseline gap-1 mt-1">
        <div class="text-lg font-semibold text-gray-900">{{ item.value }}</div>
        <div v-if="item.percentage" class="text-xs font-medium text-gray-500">
          {{ item.percentage }}
        </div>
      </div>
    </div>
  </div>
</div>





    <!-- Preview and chart -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-8">
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
        <div class="bg-white p-4 rounded-lg shadow relative min-h-[28rem] flex justify-center items-center">
          <Pie :data="dataSet" :options="{ responsive: true, maintainAspectRatio: false }" />
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
