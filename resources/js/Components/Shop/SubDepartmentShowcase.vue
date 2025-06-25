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
                v-if="data.subDepartment.image"
                :src="data.subDepartment.image"
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


      </div>


    </div>
  </div>
</template>

