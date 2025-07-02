<script setup lang="ts">
import { ref, toRefs, watch, inject } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from 'axios'
import { debounce } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

// Props
const props = defineProps<{
  modelValue: {
    family: {
      id: number
      name: string
      description: string
      description_title?: string
      description_extra?: string
      images: { source: string }
    }
  }
  webpageData?: {
    images_upload_route?: {
      name: string
    }
  }
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock: number
  update_route: {
    name: string
    parameters: Record<string, any>
  }
  data: {
    id: number
  }
}>()

const { modelValue, webpageData, blockData } = toRefs(props)

const departmentEdit = ref(false)
const descriptionTitle = ref(modelValue.value.family.description_title || '')
const showExtra = ref(false)
const layout: any = inject("layout", {})

const toggleShowExtra = () => {
  showExtra.value = !showExtra.value
}

// Debounced save function
const saveDescription = debounce(async (key: string, value: string) => {
  try {
    const url = route('grp.models.product_category.update', {
      productCategory: modelValue.value.family.id,
    })
    await axios.patch(url, { [key]: value })
    departmentEdit.value = false
  } catch (error: any) {
    console.error('Save failed:', error)
    notify({
      title: 'Failed to Save',
      text: error?.response?.data?.message || 'Please check your input and try again.',
      type: 'error',
    })
  }
}, 1000)

watch(descriptionTitle, (val) => {
  modelValue.value.family.description_title = val
  saveDescription('description_title', val)
})
</script>

<template>
  <div id="family-1">
    <div :style="{ ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), width: 'auto' }"
      class="py-4 space-y-6">

      <!-- Description Title -->
      <input v-model="descriptionTitle" type="text" placeholder="Family Description Title"
        class="w-full appearance-none bg-transparent border-none p-0 m-0 text-[1.5rem] leading-[2rem] font-semibold text-gray-800 focus:outline-none focus:ring-0 shadow-none" />


      <!-- Main Description Editor -->
      <EditorV2 v-model="modelValue.family.description" placeholder="Family Description"
        @update:model-value="(e) => saveDescription('description', e)" :uploadImageRoute="{
          name: webpageData?.images_upload_route?.name,
          parameters: { modelHasWebBlocks: blockData?.id }
        }" />

      <!-- Read More / Extra Description Block -->
      <div class="rounded-lg">
        <transition name="fade">
          <div v-if="showExtra">
            <EditorV2 v-model="modelValue.family.description_extra" placeholder="Extra Description"
              @update:model-value="(e) => saveDescription('description_extra', e)" :uploadImageRoute="{
                name: webpageData?.images_upload_route?.name,
                parameters: { modelHasWebBlocks: blockData?.id }
              }" />
          </div>
        </transition>
        <button @click="toggleShowExtra"
          class="text-sm text-gray-600 hover:underline focus:outline-none transition-colors">
          {{ showExtra ? 'show less' : 'Read More' }}
        </button>
      </div>
    </div>

  </div>

</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

#tiptap {
  margin-top: 0px;
}
</style>
