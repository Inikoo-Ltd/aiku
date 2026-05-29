<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import Dialog from 'primevue/dialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import Image from '../Common/Components/Image.vue'
import Pagination from '@/Components/Table/Pagination.vue'
import { routeType } from '@/types/route'
import { debounce } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
  modelValue?: any[]
  routeFetchDepartment: routeType
  routeFetchSubDepartment: routeType
  routeFetchFamily: routeType
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', val: any[]): void
}>()

const showDialog = ref(false)
const list = ref<any[]>([])
const meta = ref<any>(null)
const links = ref<any>(null)
const query = ref('')
const isLoading = ref(false)

const selected = ref<any[]>([])

const activeTab = ref(0)

const currentType = computed(() => {
  switch (activeTab.value) {
    case 0:
      return 'department'

    case 1:
      return 'sub_department'

    case 2:
      return 'family'

    default:
      return 'department'
  }
})

const currentRoute = computed(() => {
  switch (activeTab.value) {
    case 0:
      return props.routeFetchDepartment

    case 1:
      return props.routeFetchSubDepartment

    case 2:
      return props.routeFetchFamily

    default:
      return props.routeFetchDepartment
  }
})

const open = () => {
  selected.value = [...(props.modelValue || [])]
  showDialog.value = true

  fetchList()
}

const fetchList = async (url?: string) => {
  isLoading.value = true

  try {
    const params = {
      ...(currentRoute.value?.parameters || {})
    }

    if (!url && query.value) {
      params['filter[global]'] = query.value
    }

    const response = await axios.get(
      url || route(currentRoute.value.name, params)
    )

    list.value = response.data.data || []
    meta.value = response.data.meta
    links.value = response.data.links
  } catch (error) {
    console.error(error)
  } finally {
    isLoading.value = false
  }
}

const debounceFetch = debounce(() => {
  fetchList()
}, 400)

watch(activeTab, () => {
  query.value = ''
  fetchList()
})

const toggleSelect = (item: any) => {
  const key = `${currentType.value}-${item.id}`

  const exists = selected.value.find(
    selectedItem => selectedItem.key === key
  )

  if (exists) {
    selected.value = selected.value.filter(
      selectedItem => selectedItem.key !== key
    )
  } else {
    selected.value.push({
      ...item,
      key,
      type: currentType.value
    })
  }
}

const isSelected = (id: number) => {
  return selected.value.some(
    item =>
      item.id === id &&
      item.type === currentType.value
  )
}

const applySelection = () => {
  emits('update:modelValue', [...selected.value])
  showDialog.value = false
}

onMounted(() => {
  fetchList()
})

defineExpose({
  open
})
</script>

<template>
  <Dialog v-model:visible="showDialog" modal header="Select Item" :style="{ width: '70%' }">
    <div class="container">
       <TabView v-model:activeIndex="activeTab">
      <TabPanel header="Department" />
      <TabPanel header="Sub Department" />
      <TabPanel header="Family" />
    </TabView>

    <div class="my-3">
      <PureInput v-model="query" placeholder="Search..." @update:modelValue="debounceFetch" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-[400px] overflow-auto">
      <div v-for="item in list" :key="`${currentType}-${item.id}`"
        class="border rounded p-2 cursor-pointer relative transition" :class="isSelected(item.id)
            ? 'bg-indigo-100 border-indigo-400'
            : 'hover:bg-gray-50 border-gray-200'
          " @click="toggleSelect(item)">
        <FontAwesomeIcon v-if="isSelected(item.id)" icon="fas fa-check-circle"
          class="absolute top-2 right-2 text-green-500" />

        <Image v-if="item.image" :src="item.image.thumbnail" class="w-full h-20 object-cover mb-2 rounded" />

        <div class="text-sm font-medium">
          {{ item.name }}
        </div>

        <div v-if="item.code" class="text-xs text-gray-400">
          {{ item.code }}
        </div>

        <div class="text-xs text-indigo-500 mt-1 capitalize">
          {{ currentType.replace('_', ' ') }}
        </div>
      </div>

      <div v-if="!isLoading && !list.length" class="col-span-full text-center text-gray-400 py-6">
        {{ trans('No data found') }}
      </div>

      <div v-if="isLoading" class="col-span-full text-center py-6">
        {{ trans('Loading...') }}
      </div>
    </div>

    <div class="mt-4">
      <Pagination v-if="meta" :meta="meta" :links="links" :has-data="true" :on-click="fetchList" />
    </div>

   

    </div>
   

     <template #footer>
      <div class="flex justify-between items-center w-full">
        <div class="text-xs text-gray-500">
          {{ selected.length }}
          {{ trans('selected') }}
        </div>

        <div class="flex gap-2">
          <Button label="Cancel" type="secondary" @click="showDialog = false" />

          <Button label="Add Selected" type="create" :disabled="!selected.length" @click="applySelection" />
        </div>
      </div>
    </template>
  </Dialog>
</template>


<style scoped>
.container :deep(.p-tabview-panels) {
  padding: 0;
}
</style>