<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from '@/Components/Elements/Buttons/Button.vue'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import Image from '../Common/Components/Image.vue'
import Pagination from '@/Components/Table/Pagination.vue'
import { routeType } from '@/types/route'
import { debounce } from 'lodash-es'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
  modelValue?: any[]
  routeFetch: routeType
}>()

const emits = defineEmits<{
  (e: "update:modelValue", val: any[]): void
}>()

const showDialog = ref(false)
const list = ref<any[]>([])
const meta = ref<any>(null)
const links = ref<any>(null)
const query = ref('')
const isLoading = ref(false)

// temp selected
const selected = ref<any[]>([])

// open dialogue
const open = () => {
  selected.value = [...(props.modelValue || [])]
  showDialog.value = true
}

// fetch list (IMPORTANT: support pagination URL)
const fetchList = async (url?: string) => {
  isLoading.value = true
  try {
    const params: any = { ...props.routeFetch.parameters }

    if (!url && query.value) {
      params['filter[global]'] = query.value
    }

    const res = await axios.get(
      url || route(props.routeFetch.name, params)
    )

    list.value = res.data.data
    meta.value = res.data.meta
    links.value = res.data.links // ✅ FIX pagination
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const debounceFetch = debounce(() => fetchList(), 400)

// toggle select
const toggleSelect = (item: any) => {
  const exists = selected.value.find(p => p.id === item.id)

  if (exists) {
    selected.value = selected.value.filter(p => p.id !== item.id)
  } else {
    selected.value.push(item)
  }
}

const isSelected = (id: number) => {
  return selected.value.some(p => p.id === id)
}

// confirm
const applySelection = () => {
  emits("update:modelValue", [...selected.value])
  showDialog.value = false
}

onMounted(fetchList)

defineExpose({ open })
</script>

<template>
  <Dialog 
    v-model:visible="showDialog" 
    modal 
    header="Select Product" 
    :style="{ width: '70%' }"
  >

    <!-- SEARCH -->
    <div class="my-3">
      <PureInput 
        v-model="query" 
        @update:modelValue="debounceFetch"
        placeholder="Search..." 
      />
    </div>

    <!-- LIST -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-[400px] overflow-auto">
      
      <div 
        v-for="item in list" 
        :key="item.id"
        @click="toggleSelect(item)"
        class="border rounded p-2 cursor-pointer relative transition"
        :class="isSelected(item.id)
          ? 'bg-indigo-100 border-indigo-400'
          : 'hover:bg-gray-50 border-gray-200'"
      >
        <FontAwesomeIcon 
          v-if="isSelected(item.id)"
          icon="fas fa-check-circle"
          class="absolute top-2 right-2 text-green-500"
        />

        <Image 
          v-if="item.image" 
          :src="item.image.thumbnail"
          class="w-full h-20 object-cover mb-2 rounded"
        />

        <div class="text-sm font-medium">
          {{ item.name }}
        </div>

        <div class="text-xs text-gray-400">
          {{ item.code }}
        </div>
      </div>

      <!-- EMPTY -->
      <div v-if="!isLoading && !list.length" class="col-span-full text-center text-gray-400 py-6">
        {{ trans('No products found') }}
      </div>

    </div>

    <!-- PAGINATION -->
    <div class="mt-4">
      <Pagination 
        v-if="meta"
        :meta="meta"
        :links="links"
        :has-data="true"
        :on-click="fetchList"
      />
    </div>

    <!-- FOOTER -->
    <template #footer>
      <div class="flex justify-between items-center w-full">
        
        <div class="text-xs text-gray-500">
          {{ selected.length }} selected
        </div>

        <div class="flex gap-2">
          <Button 
            label="Cancel" 
            type="secondary" 
            @click="showDialog = false" 
          />
          <Button 
            label="Add Selected" 
            type="create"
            :disabled="!selected.length"
            @click="applySelection"
          />
        </div>

      </div>
    </template>

  </Dialog>
</template>