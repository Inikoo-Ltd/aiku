<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, watch, computed } from "vue"
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

// Ensure modelValue is initialized to an object with `family` inside it
const internalModel = ref(
  props.modelValue ?? {
    family: { code: '', settings: null }
  }
)

watch(() => props.modelValue, (val) => {
  if (val) internalModel.value = val
})

const family = computed({
  get: () => internalModel.value.family ?? { code: '', settings: null },
  set: (val) => {
    internalModel.value.family = val
    emit('update:modelValue', internalModel.value)
  }
})

const usedTemplates = ref(
  family.value.code
    ? props.data.web_block_types.data.find((t) => t.code === family.value.code)
    : props.data.web_block_types.data[0]
)

const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  usedTemplates.value = template
  family.value = {
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
    <!-- Sidebar -->
    <div class="col-span-1 flex flex-col border-r border-gray-300 shadow-lg relative overflow-auto">
      <div class="px-4 py-3 rounded-t-lg shadow">
        <div class="flex items-center">
          <font-awesome-icon :icon="['fas', 'chevron-left']"
            class="px-4 cursor-pointer text-gray-600 hover:text-gray-800 transition duration-200"
            @click="selectPreviousTemplate" />
          <div class="border w-full rounded-md p-2 flex justify-center cursor-pointer" @click="isModalOpen = true">
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
          v-model="family"
          :blueprint="getBlueprint(usedTemplates.code)"
          @update:model-value="onSaveWorkshop"
          :uploadImageRoute="null"
        />
      </div>
    </div>

    <!-- Preview Area -->
    <div class="bg-gray-100 h-full col-span-3 rounded-lg shadow-lg">
      <div class="bg-gray-100 px-6 py-6 h-[79vh] rounded-lg overflow-auto flex justify-center items-center">
        <div v-if="usedTemplates?.code" class="bg-white shadow-md rounded-lg w-fit">
          <section>
            <component class="w-full" :is="getIrisComponent(usedTemplates.code)"
              :fieldValue="usedTemplates.data.fieldValue" />
          </section>
        </div>
      </div>
    </div>
  </div>

  <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-2/5">
    <BlockList :onPickBlock="onPickTemplate" :webBlockTypes="data.web_block_types" scope="webpage" />
  </Modal>
</template>
