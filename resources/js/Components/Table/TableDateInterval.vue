<script setup lang="ts">
import { onBeforeMount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Select from 'primevue/select'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCalendarAlt, faChevronDown } from '@fal'
library.add(faCalendarAlt, faChevronDown)

const selectedPeriodType = ref<string>('all')

const periodOptions = [
  { label: ctrans('All Time'), value: 'all' },
  { label: ctrans('1 Year'), value: '1y' },
  { label: ctrans('1 Quarter'), value: '1q' },
  { label: ctrans('1 Month'), value: '1m' },
  { label: ctrans('1 Week'), value: '1w' },
  { label: ctrans('3 Days'), value: '3d' },
  { label: ctrans('Year to Date'), value: 'ytd' },
  { label: ctrans('Quarter to Date'), value: 'qtd' },
  { label: ctrans('Month to Date'), value: 'mtd' },
  { label: ctrans('Week to Date'), value: 'wtd' },
  { label: ctrans('Today'), value: 'tdy' },
  { label: ctrans('Last Month'), value: 'lm' },
  { label: ctrans('Last Week'), value: 'lw' },
  { label: ctrans('Last Day'), value: 'ld' }
]

watch(selectedPeriodType, (newValue) => {
  router.reload({
    data: {
      dateInterval: newValue
    },
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
  <Select
    v-model="selectedPeriodType"
    :options="periodOptions"
    optionLabel="label"
    optionValue="value"
    size="small"
    class="date-interval-select"
    :class="{ 'is-filtered': selectedPeriodType !== 'all' }"
  >
    <template #value="{ value }">
      <span class="flex items-center gap-x-1.5 whitespace-nowrap">
        <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width aria-hidden="true" />
        {{ periodOptions.find(period => period.value === value)?.label }}
      </span>
    </template>
    <template #dropdownicon>
      <FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
    </template>
  </Select>
</template>

<style scoped>
.date-interval-select {
  height: 30px;
  align-items: center;
  border-color: #d1d5db;
  border-radius: 0.25rem;
  font-size: 0.875rem;
}

.date-interval-select.is-filtered {
  border-color: var(--app-accent);
}

:deep(.p-select-label) {
  padding: 0 0.5rem;
  color: #374151;
}

:deep(.p-select-dropdown) {
  width: 1.75rem;
}
</style>
