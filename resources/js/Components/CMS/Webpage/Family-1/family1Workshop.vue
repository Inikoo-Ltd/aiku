<script setup lang="ts">
import { ref, watch } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  modelValue: {
    family: {
      id: number
      name: string
      description: string
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
  update_route: {
    name: string
    parameters: Record<string, any>
  }
  data: {
    id: number
  }
}>()

const departmentEdit = ref(false)


const saveDescription = debounce(() => {
  router.patch(
    route('grp.models.product_category.update', {
      productCategory: props.modelValue.family.id
    }),
    {
      preserveScroll: true,
      description: props.modelValue.family.description,
      onSuccess: () => {
        departmentEdit.value = false
      },
      onError: (errors) => {
        console.error('Save failed:', errors)
        notify({
          title: 'Failed to Save',
          text: 'Please check your input and try again.',
          type: 'error',
        })
      },
    }
  )
}, 3000)

console.log(props)
</script>

<template>
  <div class="px-4 sm:px-6 md:px-8 lg:px-12 xl:px-20 py-4">
    <EditorV2
      v-model="props?.modelValue?.family?.description"
      placeholder="Family Description"
      @update:model-value="saveDescription"
      :uploadImageRoute="{
        name: props.webpageData?.images_upload_route?.name,
        parameters: {
          modelHasWebBlocks: props.blockData?.id
        }
      }"
    />
  </div>
</template>
