<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, computed, watch } from "vue"
import Modal from '@/Components/Utils/Modal.vue'
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import { getIrisComponent } from "@/Composables/getIrisComponents"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight)

const props = defineProps<{
  modelValue: any
  data: {
    web_block_types: any
    category: any
  }
}>()

const emit = defineEmits(['update:modelValue'])

const isModalOpen = ref(false)

// Fallback if modelValue is not defined
const internalModel = ref(props.modelValue ?? {
  department: {
    code: '',
    settings: null
  }
})

watch(() => props.modelValue, (val) => {
  if (val) internalModel.value = val
})

const department = computed({
  get: () => internalModel.value.department ?? { code: '', settings: null },
  set: (val) => {
    internalModel.value.department = val
    emit('update:modelValue', internalModel.value)
  }
})

const usedTemplates = ref(
  department.value.code
    ? props.data.web_block_types.data.find((template) => template.code === department.value.code)
    : props.data.web_block_types.data[0]
)

const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  usedTemplates.value = template
  department.value = {
    code: template.code,
    settings: null
  }
}

const selectPreviousTemplate = () => {
  const idx = props.data.web_block_types.data.findIndex(t => t.code === usedTemplates.value?.code)
  if (idx > 0) {
    onPickTemplate(props.data.web_block_types.data[idx - 1])
  }
}

const selectNextTemplate = () => {
  const idx = props.data.web_block_types.data.findIndex(t => t.code === usedTemplates.value?.code)
  if (idx < props.data.web_block_types.data.length - 1) {
    onPickTemplate(props.data.web_block_types.data[idx + 1])
  }
}

const onSaveWorkshop = (block) => {
  console.log("Saved block:", block)
}
</script>

<template>
  <div class="h-[79vh] grid overflow-hidden grid-cols-4">
    <!-- Left Panel -->
    <div class="col-span-1 flex flex-col border-r border-gray-300 shadow-lg relative overflow-auto">
      <div class="px-4 py-3 rounded-t-lg shadow">
        <div class="flex items-center">
          <font-awesome-icon :icon="['fas', 'chevron-left']"
            class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
            @click="selectPreviousTemplate" />
          <div class="border w-full rounded-md p-2 align-center flex justify-center" @click="isModalOpen = true">
            {{ usedTemplates?.code }}
          </div>
          <font-awesome-icon :icon="['fas', 'chevron-right']"
            class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
            @click="selectNextTemplate" />
        </div>
      </div>

      <div class="px-4 py-5 flex-grow">
        <SideEditor
          v-if="usedTemplates?.code"
          v-model="department"
          :blueprint="getBlueprint(usedTemplates.code)"
          @update:modelValue="onSaveWorkshop"
          :uploadImageRoute="null"
        />
      </div>
    </div>

    <!-- Preview Panel -->
    <div class="bg-gray-100 h-full col-span-3 rounded-lg shadow-lg overflow-auto">
      <div class="bg-gray-100 px-6 py-6 h-[79vh] rounded-lg">
        <div v-if="usedTemplates?.code" class="bg-white shadow-md rounded-lg w-full p-4">
          <section>
            <component class="w-full" :is="getIrisComponent(usedTemplates.code)"
              :fieldValue="usedTemplates.data.fieldValue" />
          </section>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-2/5">
    <BlockList :onPickBlock="onPickTemplate" :webBlockTypes="data.web_block_types" scope="webpage" />
  </Modal>
</template>
