<script setup lang="ts">
import { ref, onMounted, inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import axios from 'axios'
import { useLayoutStore } from '@/Stores/layout'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useFormatTime } from "@/Composables/useFormatTime"
import InputText from 'primevue/inputtext'
import CountUp from 'vue-countup-v3'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'

const emit = defineEmits(['select-template'])

const layout = useLayoutStore()

const list = ref<any[]>([])
const loading = ref(false)
const selectedItems = ref<any[]>([])
const totalRecords = ref(0)

const locale = inject('locale', aikuLocaleStructure)

const lazyParams = ref({
  page: 0,
  rows: 10,
  sortField: null,
  sortOrder: null,
  search: ''
})

const loadData = async () => {
  loading.value = true
  try {
    const response = await axios.get(
      route('grp.json.layout_template.list_template', {
        webpage: route().params['webpage'],
        page: lazyParams.value.page + 1,
        per_page: lazyParams.value.rows,
        search: lazyParams.value.search
      })
    )

    const data = response.data
    list.value = data.data
    totalRecords.value = data.meta.total
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const onPage = (event: any) => {
  lazyParams.value.page = event.page
  lazyParams.value.rows = event.rows
  loadData()
}

const onFilter = () => {
  lazyParams.value.page = 0
  loadData()
}

onMounted(loadData)
</script>

<template>
  <div class="h-[520px] bg-white p-4 flex flex-col">
    <DataTable
      :value="list"
      v-model:selection="selectedItems"
      dataKey="id"
      lazy
      paginator
      :rows="lazyParams.rows"
      :totalRecords="totalRecords"
      :loading="loading"
      @page="onPage"
      responsiveLayout="scroll"
      selectionMode="multiple"
      scrollable
      scrollHeight="flex"
      class="text-sm flex-1"
    >
      <template #header>
        <div class="flex items-center gap-4 w-full">
          <div class="whitespace-nowrap flex gap-x-1.5 flex-nowrap bg-gray-300 p-2 rounded">
            <span class="font-semibold tabular-nums">
              <CountUp
                :endVal="totalRecords"
                :duration="1.2"
                :scrollSpyOnce="true"
                :options="{
                  formattingFn: (number) => locale.number(number)
                }"
              />
            </span>
            <span class="font-light">
              {{ trans('record') }}
            </span>
          </div>

          <div class="w-64">
            <InputText
              v-model="lazyParams.search"
              placeholder="Search..."
              @input="onFilter"
            />
          </div>
        </div>
      </template>

      <Column field="label" header="Label" sortable>
        <template #body="{ data }">
          {{ data.label }}
        </template>
      </Column>

      <Column field="author" header="Author" sortable />

      <Column header="Created At">
        <template #body="{ data }">
          {{ useFormatTime(data.created_at, { formatTime: 'MMM dd, yyyy' }) }}
        </template>
      </Column>

      <Column header="Modified At">
        <template #body="{ data }">
          {{ useFormatTime(data.updated_at, { formatTime: 'MMM dd, yyyy' }) }}
        </template>
      </Column>

      <Column header="Action" style="width:140px">
        <template #body="{ data }">
          <Button
            label="Use Template"
            size="xs"
            @click="emit('select-template', data.id)"
          />
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
:deep(.p-datatable) {
  height: 100%;
  display: flex;
  flex-direction: column;
}

:deep(.p-datatable-wrapper) {
  flex: 1;
}
</style>