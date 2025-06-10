<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign, faImage, faUnlink, faGlobe } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { Link, router } from '@inertiajs/vue3'
import Image from '@/Components/Image.vue'
import CountUp from 'vue-countup-v3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Icon from "@/Components/Icon.vue"
import { notify } from '@kyvg/vue3-notification'
import EmptyState from '@/Components/Utils/EmptyState.vue'

library.add(faDollarSign, faImage, faUnlink, faGlobe)

const locale = inject('locale', aikuLocaleStructure)
const unassignLoadingIds = ref<number[]>([])

const props = defineProps<{
  data: {
    name?: string
    image?: string
    description?: string
    id: number
    stats: {
      label: string
      icon: string
      value: number
      meta: { value: number; label: string }
    }[]
    attached_webpages: {
      id: number
      name: string
      code?: string
      title?: string
      description?: string
      image?: string[]
      typeIcon?: any
      route?: { name: string; parameters?: Record<string, any> }
    }[]
  }
  loadingUnassignIds?: number[]
}>()

const UnassignCollectionFormWebpage = async (id: number) => {
  unassignLoadingIds.value.push(id)
  const url = route("grp.models.webpage.detach_collection", {
    webpage: id,
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
        text: trans("Collection has been removed."),
        type: "success",
      })
    },
    onFinish: () => {
      unassignLoadingIds.value = unassignLoadingIds.value.filter((item) => item !== id)
    },
  })
}
</script>

<template>
  <div class="p-4 space-y-6">
    <div class="grid lg:grid-cols-[30%_1fr] gap-4 max-w-6xl">
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

      <!-- Webpages List -->
      <div class="bg-white border border-gray-200 rounded-xl shadow p-4 space-y-4">
        <h2 class="text-lg font-semibold text-gray-800">{{ trans('Webpages') }}</h2>

        <div v-if="data.attached_webpages.length" class="space-y-3">
          <div
            v-for="webpage in data.attached_webpages"
            :key="webpage.id"
            class="flex items-center gap-4 bg-gray-50 border border-gray-200 rounded-md p-3 hover:shadow transition"
          >
            <!-- Icon -->
            <Icon
              v-if="webpage?.typeIcon"
              :data="webpage.typeIcon"
              size="xl"
              class="text-gray-600 shrink-0"
            />

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-medium text-gray-800 truncate">{{ webpage.code || webpage.name }}</h3>
              <p class="text-xs text-gray-500 line-clamp-2">{{ webpage.title || trans('No title') }}</p>
            </div>

            <!-- Unassign Button -->
            <Button
              type="negative"
              size="xs"
              :icon="faUnlink"
              v-tooltip="'Unassign'"
              :loading="unassignLoadingIds.includes(webpage.id)"
              @click="UnassignCollectionFormWebpage(webpage.id)"
              class="shrink-0"
            />
          </div>
        </div>

        <div v-else class="text-sm text-gray-500 italic text-center">
         <EmptyState />
        </div>
      </div>
    </div>
  </div>
</template>
