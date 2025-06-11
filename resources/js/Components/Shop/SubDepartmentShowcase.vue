<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faUnlink, faThLarge, faBars, faSeedling, faCheck, faFolderTree } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { ref, provide } from 'vue'
import EmptyState from '../Utils/EmptyState.vue'
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Icon from "@/Components/Icon.vue"
import CollectionList from "@/Components/Departement&Family/CollectionList.vue"

library.add(faUnlink, faThLarge, faBars, faSeedling, faCheck)

const props = defineProps<{
  data: {
    subDepartment: {
      slug: string
      image_id: ImageTS | string | null
      code: string
      name: string
      state: string
      created_at: string
      updated_at: string
    }
    families: {
      id: number
      name: string
      slug: string
      image: ImageTS
      code: string
      state?: any
      products?: {
        id: number
        name: string
        image: ImageTS
      }[]
    }[]
    routes: {
      detach_family: routeType,
      attach_collections_route: routeType,
      detach_collections_route: routeType
    }
    collections: {
      data: {
        id: number
        name: string
        description?: string
        image?: ImageTS[]
      }[]
    },
    routeList: {
      collectionRoute: string,
      collections_route: string
    },
    has_wepage?: boolean
  }
}>()

const isLoadingDelete = ref<string[]>([])
const isLoadingSubmit = ref(false)
const unassignLoadingIds = ref<number[]>([])
const isModalOpen = ref(false)
provide('isModalOpen', isModalOpen)

const onDetachFamily = (slug: string) => {
  router.delete(
    route(props.data.routes.detach_family.name, {
      ...props.data.routes.detach_family.parameters,
      family: slug
    }),
    {
      onStart: () => isLoadingDelete.value.push(slug),
      onFinish: () => {
        isLoadingDelete.value = isLoadingDelete.value.filter(item => item !== slug)
      },
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: trans("Success"),
          text: "Family has been unlinked",
          type: "success",
        })
      }
    }
  )
}

const assignCollection = async (collections: any[]) => {
  const method = props.data.routes.attach_collections_route.method
  const url = route(
    props.data.routes.attach_collections_route.name,
    props.data.routes.attach_collections_route.parameters
  )
  const collectionIds = collections.map((c) => c.id)

  router[method](
    url,
    { collections: collectionIds },
    {
      onBefore: () => (isLoadingSubmit.value = true),
      onError: (error) => {
        notify({
          title: trans("Something went wrong."),
          text: error?.products || trans("Failed to add collection."),
          type: "error",
        })
      },
      onSuccess: () => {
        notify({
          title: trans("Success!"),
          text: trans("Successfully added portfolios"),
          type: "success",
        })
        isModalOpen.value = false
      },
      onFinish: () => {
        isLoadingSubmit.value = false
      },
    }
  )
}

const UnassignCollection = async (id: number) => {
  unassignLoadingIds.value.push(id)
  const method = props.data.routes.detach_collections_route.method
  const url = route(props.data.routes.detach_collections_route.name, {
    ...props.data.routes.detach_collections_route.parameters,
    collection: id,
  })

  router[method](
    url,
    {
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
          text: trans("Collection has been removed."),
          type: "success",
        })
      },
      onFinish: () => {
        unassignLoadingIds.value = unassignLoadingIds.value.filter((x) => x !== id)
      },
    }
  )
}
</script>

<template>
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- Grid Layout: SubDepartment + Collection List -->
    <div class="grid grid-cols-1 lg:grid-cols-[5fr_2fr] gap-8">

      <!-- Left: SubDepartment & Families -->
      <div class="space-y-8">

        <!-- SubDepartment Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden shadow-sm">
              <Image
                v-if="data.subDepartment.image_id"
                :src="data.subDepartment.image_id"
                :alt="data.subDepartment.name"
                class="w-full h-full object-cover"
                imageCover
              />
              <FontAwesomeIcon
                v-else
                :icon="faFolderTree"
                class="w-10 h-10 text-gray-400"
              />
            </div>

            <div>
              <h1 class="text-2xl font-bold text-gray-800">{{ data.subDepartment.name }}</h1>
              <p class="text-sm text-gray-500">Code: {{ data.subDepartment.code }}</p>
              <p class="text-sm text-gray-500">
                Status:
                <span
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  :class="{
                    'bg-green-100 text-green-700': data.subDepartment.state === 'active',
                    'bg-gray-100 text-gray-600': data.subDepartment.state !== 'active',
                  }"
                >
                  {{ data.subDepartment.state }}
                </span>
              </p>
            </div>
          </div>

          <div class="text-sm text-gray-400 sm:text-right">
            <p>Created at:</p>
            <p class="font-medium">{{ new Date(data.subDepartment.created_at).toLocaleDateString() }}</p>
          </div>
        </div>

        <!-- Family Section -->
        <div>
          <!-- Header -->
          <div class="flex items-center justify-between mb-4 border-b pb-2">
            <h2 class="text-lg font-semibold text-gray-800">
              {{ trans("Family list") }} ({{ data.families.length }})
            </h2>
          </div>

          <!-- List -->
          <div v-if="data.families?.length" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            <div
              v-for="family in data.families"
              :key="family.id"
              class="relative bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-all p-5 flex flex-col"
            >
              <span class="absolute -top-3 left-3 bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded shadow">Family</span>

              <!-- Header -->
              <div class="flex items-start gap-3 mb-4">
                <Image
                  :src="family.image"
                  :alt="family.name"
                  class="w-12 h-12 rounded-lg object-cover shadow-sm"
                  imageCover
                />
                <div class="flex-1 min-w-0">
                  <h3 class="text-base font-semibold text-gray-900 flex items-center gap-1">
                    {{ family.name }}
                    <Icon
                      v-if="family.state"
                      :data="family.state"
                      :title="family.state.label"
                      class="ml-1"
                    />
                  </h3>
                  <p class="text-xs text-gray-500">Code: {{ family.code }}</p>
                  <p class="text-xs text-gray-500">
                    {{ family.products?.length || 0 }} product{{ family.products?.length === 1 ? '' : 's' }}
                  </p>
                </div>

                <Button
                  @click.stop="onDetachFamily(family.slug)"
                  icon="fal fa-unlink"
                  type="negative"
                  size="xs"
                  :loading="isLoadingDelete.includes(family.slug)"
                />
              </div>

              <!-- Products -->
              <div class="bg-gray-50 border rounded-lg px-4 py-2 max-h-56 overflow-y-auto custom-scroll space-y-2">
                <template v-if="family.products?.length">
                  <div
                    v-for="product in family.products"
                    :key="product.id"
                    class="flex items-start gap-3 border-b last:border-b-0 py-2"
                  >
                    <FontAwesomeIcon :icon="['fal', 'seedling']" class="w-4 h-4 text-green-500 mt-1" />
                    <div class="w-8 h-8 rounded overflow-hidden flex-shrink-0">
                      <Image
                        :src="product.image?.source"
                        :alt="product.name"
                        class="w-full h-full object-cover"
                        imageCover
                      />
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-sm text-gray-700 truncate">{{ product.name }}</p>
                      <p class="text-xs text-gray-500">Code: {{ product.id }}</p>
                    </div>
                  </div>
                </template>
                <div v-else class="text-xs text-gray-400 italic text-center">No products available.</div>
              </div>
            </div>
          </div>

          <!-- Empty -->
          <div v-else class="mx-auto max-w-md px-4 py-20 text-center">
            <EmptyState :data="{ title: 'No families', description: 'This subdepartment has no families' }" />
          </div>
        </div>
      </div>

      <!-- Right: Collection List -->
      <CollectionList  v-if="data.has_webpage"
        :collections="props.data.collections.data"
        :routeFetch="props.data.routeList.collections_route"
        :canAdd="true"
        :loadingUnassignIds="unassignLoadingIds"
        :isSubmitting="isLoadingSubmit"
        @assign="assignCollection"
        @unassign="UnassignCollection"
      />
    </div>
  </div>
</template>

