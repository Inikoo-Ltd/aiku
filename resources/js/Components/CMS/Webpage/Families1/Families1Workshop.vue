<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'

import Family1Render from './Families1Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import FormEditProductCategory from "@/Components/DepartmentAndFamily/FormEditProductCategory.vue"
import Dialog from 'primevue/dialog'
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from './Blueprint'
import { routeType } from '@/types/route'

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  modelValue: {
    families: {
      id: number
      name: string
      description: string
      description_extra?: string
      description_title?: string
      image?: string
      images?: { source: string }[]
    }[]
    collections?: {
      id: number
      name: string
      description: string
      image?: string
    }[]
    container?: { properties?: any }
    settings?: {
      per_row?: {
        desktop?: number
        tablet?: number
        mobile?: number
      }
    }
  }
  routeEditfamily?: routeType
  webpageData?: any
  blockData?: Object
  indexBlock?: number
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

// Selected sub-department for editing modal
const selectedSubDepartment = ref<null | {
  id: number
  name: string
  description: string
  description_extra?: string
  description_title?: string
  image?: string
}>(null)

/* const showDialog = ref(false) */
console.log('ssss',props)
const layout: any = inject("layout", {})
const visibleDrawer = inject('visibleDrawer', undefined)

const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

const allItems = computed(() => [
  ...(props.modelValue?.families || []),
  ...(props.modelValue?.collections || [])
])

const responsiveGridClass = computed(() => {
  const perRow = props.modelValue?.settings?.per_row ?? {}
  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }
  return `grid-cols-${columnCount[props.screenType] ?? 1}`
})

// Activate block for parent communication
function activateBlock() {
  sendMessageToParent('activeBlock', props.indexBlock)
  sendMessageToParent('activeChildBlock', bKeys[0])
}

// Open modal for editing sub-department
/* function openModal(subDept: any) {
  if (props.routeEditfamily) {
    selectedSubDepartment.value = { ...subDept }
    showDialog.value = true
  }
} */

// Handle saved changes from modal
/* function handleSaved(updatedSubDept: any) {
  const index = props.modelValue.families.findIndex(item => item.id === updatedSubDept.id)
  if (index !== -1) {
    props.modelValue.families[index] = { ...props.modelValue.families[index], ...updatedSubDept }
  }
  closeModal()
} */

/* function closeModal() {
  showDialog.value = false
  selectedSubDepartment.value = null
} */
</script>

<template>
  <div id="families-1">
    <div
      v-if="allItems.length"
      class="px-4 py-10 mx-[30px]"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.modelValue.container?.properties, props.screenType)
      }"
      @click="activateBlock"
    >
      <h2 class="text-2xl font-bold mb-6">Browse By Product Lines:</h2>

      <div :class="['grid gap-8', responsiveGridClass]">
        <div v-for="(item, index) in allItems" :key="`item-${index}`">
          <Family1Render :data="item" />
        </div>
      </div>
    </div>

    <EmptyState v-else :data="{ title: 'Empty Families' }">
      <template v-if="visibleDrawer !== undefined" #button-empty-state>
        <Button
          label="Select sub-department to preview family list"
          type="secondary"
        />
      </template>
    </EmptyState>

    <!-- Edit Modal -->
   <!--  <Dialog
      v-model:visible="showDialog"
      :header="`Edit ${selectedSubDepartment?.name}`"
      :style="{ width: '500px' }"
      :closable="true"
      @hide="closeModal"
      :modal="true"
    >
      <FormEditProductCategory
        v-if="selectedSubDepartment"
        :key="selectedSubDepartment.id"
        :data="selectedSubDepartment"
        :saveRoute="props.routeEditfamily"
        @saved="handleSaved"
      />
    </Dialog> -->
  </div>
</template>
