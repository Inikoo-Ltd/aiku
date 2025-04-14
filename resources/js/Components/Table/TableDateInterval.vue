<script setup lang="ts">
import { onBeforeMount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Select from 'primevue/select'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faWatchCalculator } from '@fas'
library.add( faWatchCalculator)

const selectedPeriodType = ref<string>('all')

const periodOptions = [
  { label: 'All Time', value: 'all' },
  { label: '1 Year', value: '1y' },
  { label: '1 Quarter', value: '1q' },
  { label: '1 Month', value: '1m' },
  { label: '1 Week', value: '1w' },
  { label: '3 Days', value: '3d' },
  { label: 'Year to Date', value: 'ytd' },
  { label: 'Quarter to Date', value: 'qtd' },
  { label: 'Month to Date', value: 'mtd' },
  { label: 'Week to Date', value: 'wtd' },
  { label: 'Today', value: 'tdy' },
  { label: 'Last Month', value: 'lm' },
  { label: 'Last Week', value: 'lw' },
  { label: 'Last Day', value: 'ld' }
]

watch(selectedPeriodType, (newValue) => {
  router.reload({
    data: {
      dateInterval: newValue
    },
    onStart: () => console.log('Reloading...'),
    onFinish: () => console.log('Done.'),
    headers: {
      'X-Timezone': Intl.DateTimeFormat().resolvedOptions().timeZone
    }
  })
})

onBeforeMount(() => {
  const urlParams = new URLSearchParams(window.location.search)
  const interval = urlParams.get('dateInterval')
  if (interval && periodOptions.some(p => p.value === interval)) {
    selectedPeriodType.value = interval
  }
})
</script>

<template>
   <div class="select-container">
  
    <Select
      v-model="selectedPeriodType"
      :options="periodOptions"
      optionLabel="label"
      optionValue="value"
      class="custom-select"
    >
    <template #dropdownicon>
      <FontAwesomeIcon
      icon="fas fa-watch-calculator"
      aria-hidden="true"
      fixed-width
      class="text-gray-500 mr-2"
    />
    </template>
    </Select>
  </div>
  </template>
  
  <style  scoped>
  .select-container {
    display: flex;
    align-items: center;
    min-width: 100px;
    max-width: 160px;
  }
  
  .custom-select {
    width: 100%;
    font-weight: 500;
    font-size: 0.9rem;
    border-radius: 6px;
    border: 1px solid #d0d5dd;
    background-color: white;
  }
  
  :deep(.p-select-label) {
  padding: 0.5rem 0.75rem;
  color: #344054;
}

  
  .p-dropdown {
    border-radius: 6px;
    box-shadow: none;
  }
  
  .p-dropdown-panel .p-dropdown-items .p-dropdown-item {
    padding: 0.5rem 0.75rem;
  }
  </style>
  
