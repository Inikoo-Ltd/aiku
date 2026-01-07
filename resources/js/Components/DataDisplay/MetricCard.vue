<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Chart from "primevue/chart"

defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  change: {
    type: String,
    required: true,
  },
  changeType: {
    type: String,
    required: true,
    validator: (value) => ["increase", "decrease"].includes(value),
  },
  chartData: {
    type: Object,
    required: true,  // Chart data is required for each MetricCard
  },
  chartOptions: {
    type: Object,
    required: true,  // Chart options are required for each MetricCard
  },
})
</script>

<template>
  <div class="stat-card p-4 border rounded-lg shadow-xs bg-white flex flex-col justify-between">
    <div class="flex justify-between items-center mb-2">
      <div>
        <h4 class="text-sm font-medium">{{ title }}</h4>
        <p class="text-2xl font-bold">{{ value }}</p>
      </div>
      <div class="flex items-center">
        <FontAwesomeIcon
          :icon="['fal', changeType === 'increase' ? 'arrow-up' : 'arrow-down']"
          :class="changeType === 'increase' ? 'text-green-500' : 'text-red-500'" />
        <span
          :class="changeType === 'increase' ? 'text-green-500' : 'text-red-500'"
          class="text-sm ml-1">
          {{ change }}
        </span>
      </div>
    </div>

    <!-- Chart Section -->
    <div v-if="chartData && chartOptions" class="flex-grow">
      <Chart type="line" :data="chartData" :options="chartOptions" class="h-32 w-full" />
    </div>
  </div>
</template>

<style scoped>
.stat-card {
  border: 1px solid #0078d4;
  transition: box-shadow-sm 0.2s ease-in-out;
}
.stat-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
h4 {
  margin-bottom: 0.25rem;
}
</style>
