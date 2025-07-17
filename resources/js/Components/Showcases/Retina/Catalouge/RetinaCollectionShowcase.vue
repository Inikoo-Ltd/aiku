<script setup lang="ts">
import { ref, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign, faImage, faUnlink, faGlobe } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { router } from '@inertiajs/vue3'

import Image from '@/Components/Image.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Icon from "@/Components/Icon.vue"
import { notify } from '@kyvg/vue3-notification'
import Modal from '@/Components/Utils/Modal.vue'
import CollectionSelector from '@/Components/Departement&Family/CollectionSelector.vue'
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
  <div class="p-4 space-y-6">
    <div class="grid lg:grid-cols-[30%_40%] gap-4 max-w-6xl">
      <!-- Info Card -->
      <div class="bg-white border border-gray-200 rounded-xl shadow p-4 space-y-3 h-fit">
        <div class="bg-white rounded-lg overflow-hidden">
          <Image v-if="data.image" :src="data.image" imageCover class="w-full h-36 object-cover" />
          <div v-else class="h-36 flex items-center justify-center bg-gray-100 flex-col">
            <FontAwesomeIcon :icon="faImage" class="text-gray-400 w-6 h-6" />
            <span class="text-xs text-gray-500">{{ trans('No image') }}</span>
          </div>
        </div>
        <div class="border-t pt-3 text-sm space-y-1 text-gray-700">
          <div class="text-base font-semibold">{{ data.name || trans('No label') }}</div>
          <div class="text-gray-500">{{ data.description || trans('No description') }}</div>
        </div>
      </div>
    </div>
  </div>

</template>
