<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faChevronLeft, faChevronRight } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, provide } from "vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import SideMenuDepartementWorkshop from "./SideMenuDepartementWorkshop.vue"
import ScreenView from "@/Components/ScreenView.vue"
import { getIrisComponent } from "@/Composables/getIrisComponents"

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
const usedTemplates = ref(null)
const onPickTemplate = (template: any) => {
  isModalOpen.value = false
  usedTemplates.value = template
}


const currentView = ref('desktop')
provide('currentView', currentView)

</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuDepartementWorkshop :data="usedTemplates" :webBlockTypes="data.web_block_types"
        @set-up-template="onPickTemplate" />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-hidden border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <ScreenView @screenView="(e) => { currentView = e }" v-model="currentView" />
      </div>
      <div v-if="usedTemplates?.code" class="relative flex-1 overflow-hidden">
        <component class="w-full" :is="getIrisComponent(usedTemplates.code)"
          :fieldValue="usedTemplates.data.fieldValue" />
      </div>
      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>
</template>


<style scoped></style>
