<script setup lang="ts">
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from '../Elements/Buttons/Button.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInfinity, faPlus, faTrash, faPen, faPencil } from '@far'
import { faEdit } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureMultiselect from './PureMultiselect.vue'
import PureTextarea from './PureTextarea.vue'

library.add(faInfinity, faPlus, faTrash, faPen)

const props = withDefaults(defineProps<{
  modelValue: Array<{
    country_code: string
    included_postal_codes?: string
    excluded_postal_codes?: string
  }>
  country_list?: any
}>(), {
  country_list: () => [],
})

const emit = defineEmits(['update:modelValue'])

const items = computed(() => props.modelValue || [])

const showModal = ref(false)
const selectedIndex = ref(-1)
const editCountryCode = ref('')
const editIncludedPostalCodes = ref('')
const editExcludedPostalCodes = ref('')

const countryOptions = computed(() => {
  const list = props.country_list
  if (list && typeof list === 'object' && !Array.isArray(list)) {
    return Object.values(list).map((c: any) => ({
      label: c.label,
      code: (c.label.match(/\(([^)]+)\)/)?.[1] || '').toUpperCase(),
    }))
  }
  if (Array.isArray(list)) {
    return list.map((c: any) => ({
      label: c.label,
      code: (c.label.match(/\(([^)]+)\)/)?.[1] || '').toUpperCase(),
    }))
  }
  return []
})

function openEditModal(index: number) {
  const item = props.modelValue[index]
  selectedIndex.value = index
  editCountryCode.value = item.country_code
  editIncludedPostalCodes.value = item.included_postal_codes || ''
  editExcludedPostalCodes.value = item.excluded_postal_codes || ''
  showModal.value = true
}

function addNewItem() {
  selectedIndex.value = -1
  editCountryCode.value = ''
  editIncludedPostalCodes.value = ''
  editExcludedPostalCodes.value = ''
  showModal.value = true
}

function saveEdit() {
  const updated = [...props.modelValue]
  const newItem = {
    country_code: editCountryCode.value,
    included_postal_codes: editIncludedPostalCodes.value,
    excluded_postal_codes: editExcludedPostalCodes.value,
  }

  if (selectedIndex.value >= 0) {
    updated[selectedIndex.value] = newItem
  } else {
    updated.push(newItem)
  }

  emit('update:modelValue', updated)
  showModal.value = false
}

function deleteItem(index: number) {
  const updated = [...props.modelValue]
  updated.splice(index, 1)
  emit('update:modelValue', updated)
}

function getCountryLabel(code: string): string {
  const found = countryOptions.value.find(c => c.code === code)
  return found ? found.label : code
}
</script>

<template>
  <div class="space-y-4 text-sm max-w-[450px] mx-auto">
    <!-- Add Button -->
    <div class="flex justify-end mb-2">
      <Button :icon="faPlus" label="Add Region" type="add" size="xs" @click="addNewItem" />
    </div>

    <!-- List of Regions -->
    <div
      v-for="(item, index) in items"
      :key="index"
      class="p-3 rounded border border-gray-300 bg-white shadow-sm space-y-2"
    >
      <!-- Country Code -->
      <div class="flex justify-between items-center">
        <div>
          <span class="text-gray-500">Country:</span>
          <span class="font-mono font-medium text-black">
            <img
              class="inline pr-1 pl-1 h-[1em]"
              :src="'/flags/' + item.country_code.toLowerCase() + '.png'"
              :alt="item.country_code"
              :title="getCountryLabel(item.country_code)"
            />
            {{ getCountryLabel(item.country_code) }}
          </span>
        </div>

        <!-- Edit/Delete Buttons -->
        <div class="flex items-center ">
          <Button :icon="faEdit" type="edit" size="xs" @click="openEditModal(index)" >
             <template #icon>
              <FontAwesomeIcon :icon="faPencil" class="w-4 h-4" />
            </template>
          </Button>
          <Button :icon="faTrash" type="edit" class="text-red-500" size="xs" @click="deleteItem(index)" >
            <template #icon>
              <FontAwesomeIcon :icon="faTrash" class="text-red-500 w-4 h-4" />
            </template>
          </Button>
        </div>
      </div>

      <!-- Included Postal Codes -->
      <div v-if="item.included_postal_codes">
        <div class="text-gray-500 mb-1">Included Postal Codes</div>
        <div class="bg-gray-50 text-xs p-2 rounded text-black whitespace-pre-wrap break-words">
          {{ item.included_postal_codes }}
        </div>
      </div>

      <!-- Excluded Postal Codes -->
      <div v-if="item.excluded_postal_codes">
        <div class="text-gray-500 mb-1">Excluded Postal Codes</div>
        <div class="bg-gray-50 text-xs p-2 rounded text-black whitespace-pre-wrap break-words">
          {{ item.excluded_postal_codes }}
        </div>
      </div>
    </div>
  </div>

 <!-- Modal for Add/Edit -->
<!-- Modal for Add/Edit -->
<Dialog v-model:visible="showModal" modal header="Region Editor" :style="{ width: '450px' }">
  <div class="space-y-4 text-sm">
    <!-- Add mode -->
    <div v-if="selectedIndex === -1">
      <label class="block mb-1 text-gray-600">Country</label>
      <PureMultiselect
        :modelValue="editCountryCode"
        @update:modelValue="val => editCountryCode = val"
        :options="countryOptions"
        :searchable="true"
        label="label"
        valueProp="code"
        mode="single"
        required
      />
    </div>

    <!-- Edit mode -->
    <div v-else>
      <label class="block mb-1 text-gray-600">Country</label>
      <div class="flex items-center font-medium text-black bg-gray-50 p-2 rounded">
        <img
          class="inline pr-1 pl-1 h-[1em]"
          :src="'/flags/' + editCountryCode.toLowerCase() + '.png'"
          :alt="editCountryCode"
          :title="getCountryLabel(editCountryCode)"
        />
        {{ getCountryLabel(editCountryCode) }}
      </div>
    </div>

    <div>
      <label class="block mb-1 text-gray-600">Included Postal Codes</label>
      <PureTextarea v-model="editIncludedPostalCodes" rows="3" class="w-full" autoResize />
    </div>

    <div>
      <label class="block mb-1 text-gray-600">Excluded Postal Codes</label>
      <PureTextarea v-model="editExcludedPostalCodes" rows="3" class="w-full" autoResize />
    </div>
  </div>

  <template #footer>
    <Button label="Cancel" type="exit" @click="showModal = false" />
    <Button type="create" :label="selectedIndex === -1 ? 'Add' : 'Set Changes'" @click="saveEdit" />
  </template>
</Dialog>

</template>
