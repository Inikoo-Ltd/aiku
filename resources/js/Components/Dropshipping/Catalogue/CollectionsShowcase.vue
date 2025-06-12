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
      detach_parent : routeType
    }
  }
}>()
console.log(props)

const isModalOpenDepartment = ref(false)
const isModalOpenSubDepartment = ref(false)
const loading = ref(false)
const unassignLoadingIds = ref<number[]>([])

const UnassignCollectionFormWebpage = async (id: number) => {
  unassignLoadingIds.value.push(id)
  const url = route(props.data.routes.detach_parent.name,{
    productCategory: id,
    collection: props.data.id,
  })

  router.delete(url, {
    onError: (error) => {
      notify({
        title: trans("Something went wrong."),
        text: error?.products || trans("Failed to remove collection."),
        type: "error",
      })
    },
    onSuccess: () => {
      notify({
        title: trans("Success!"),
        text: trans("Parent has been removed."),
        type: "success",
      })
    },
    onFinish: () => {
      unassignLoadingIds.value = unassignLoadingIds.value.filter(item => item !== id)
    },
  })
}

const attachToparent = async (key : string , data: { id: number }[]) => {
  loading.value = true
  const ids = data.map(item => item.id)

  router.post(
    route(props.data.routes.attach_parent.name, props.data.routes.attach_parent.parameters),
    { [key]: ids },
    {
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: trans('Success!'),
          text: trans('edit Webpages  successfully.'),
          type: 'success',
        })
        isModalOpenDepartment.value = false
        isModalOpenSubDepartment.value = false
      },
      onError: (errors) => {
        notify({
          title: trans('Error'),
          text: errors?.ids || trans('Failed edit webpages.'),
          type: 'error',
        })
      },
      onFinish: () => {
        loading.value = false
      },
    }
  )
}
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

      <!-- Department List -->
      <div class="space-y-6">
        <!-- Department Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow p-4 space-y-4">
          <div>
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Department</h2>
              <Button type="create" size="xs" @click="isModalOpenDepartment = true" :label="'Department'" />
            </div>
            <hr class="mt-2 border-gray-200" />
          </div>

          <div v-if="data.parent_departments.length" class="space-y-1 max-h-64 overflow-auto">
            <div v-for="dept in data.parent_departments" :key="dept.id"
              class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-md p-3 hover:shadow-sm transition">
             <!--  <Icon v-if="dept?.typeIcon" :data="dept.typeIcon" size="lg" class="text-gray-600 shrink-0" /> -->
              <div class="flex-1 min-w-0">
                <h3 class="text-sm font-medium text-gray-800 truncate">{{ dept.code || dept.name }}</h3>
                <p class="text-xs text-gray-500 line-clamp-2">{{ dept.name || 'No name' }}</p>
              </div>
              <Button type="negative" size="xs" :icon="faUnlink" v-tooltip="'Unassign'"
                :loading="unassignLoadingIds.includes(dept.id)" @click="UnassignCollectionFormWebpage(dept.id)"
                class="shrink-0" />
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 italic text-center py-2">
            No departments assigned.
          </div>
        </div>

        <!-- Sub Department Card -->
        <div class="bg-white border border-gray-200 rounded-xl shadow p-4 space-y-4">
          <div>
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Sub Department</h2>
              <Button type="create" size="xs" @click="isModalOpenSubDepartment = true" :label="'Sub-Department'" />
            </div>
            <hr class="mt-2 border-gray-200" />
          </div>

          <div v-if="data.parent_subdepartments.length" class="space-y-1 max-h-64 overflow-auto">
            <div v-for="dept in data.parent_subdepartments" :key="dept.id"
              class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-md p-3 hover:shadow-sm transition">
            <!--   <Icon v-if="dept?.typeIcon" :data="dept.typeIcon" size="lg" class="text-gray-600 shrink-0" /> -->
              <div class="flex-1 min-w-0">
                <h3 class="text-sm font-medium text-gray-800 truncate">{{ dept.code || dept.name }}</h3>
                <p class="text-xs text-gray-500 line-clamp-2">{{ dept.title || 'No title' }}</p>
              </div>
              <Button type="negative" size="xs" :icon="faUnlink" v-tooltip="'Unassign'"
                :loading="unassignLoadingIds.includes(dept.id)" @click="UnassignCollectionFormWebpage(dept.id)"
                class="shrink-0" />
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 italic text-center py-2">
            No sub departments assigned.
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modals -->
  <Modal :isOpen="isModalOpenDepartment" @onClose="isModalOpenDepartment = false" width="w-full max-w-6xl">
    <CollectionSelector :headLabel="`${trans('Add Departement to collection')}`" :routeFetch="{
      name: data.routes.departments_route.name,
      parameters: data.routes.departments_route.parameters
    }" :isLoadingSubmit="loading" @submit="(ids)=>attachToparent('departments',ids)" />
  </Modal>

  <Modal :isOpen="isModalOpenSubDepartment" @onClose="isModalOpenSubDepartment = false" width="w-full max-w-6xl">
    <CollectionSelector :headLabel="`${trans('Add Sub-Departement to collection')}`" :routeFetch="{
      name: data.routes.sub_departments_route.name,
      parameters: data.routes.sub_departments_route.parameters
    }" :isLoadingSubmit="loading" @submit="(ids)=>attachToparent('sub_departments',ids)" />
  </Modal>
</template>
