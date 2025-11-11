<script setup lang="ts">
import { inject } from 'vue'
import { faDollarSign, faImage, faUnlink, faGlobe } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import { routeType } from '@/types/route'

library.add(faDollarSign, faImage, faUnlink, faGlobe)

const locale = inject('locale', aikuLocaleStructure)
const props = defineProps<{
  data: {
    id: number
    slug: string
    name?: string
    image?: string
    description?: string
    stats: Array<{
      label: string
      icon: string
      value: number
      meta: {
        value: number
        label: string
      }
    }>
    parent_departments: any[]
    parent_subdepartments: any[]
    routes: {
      attach_parent: { name: string; parameters: any }
      departments_route: { name: string; parameters: any }
      sub_departments_route: { name: string; parameters: any }
      detach_parent: routeType
    }
  }
}>()





</script>

<template>
   <div class="p-4 grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-4 mt-4">
      <div class="col-span-1 md:col-span-1 lg:col-span-2">
        <ProductCategoryCard :data="data" />
      </div>
    </div>

</template>
