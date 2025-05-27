<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faUnlink, faThLarge, faBars } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { ref } from 'vue'
import EmptyState from '../Utils/EmptyState.vue'
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faUnlink, faThLarge, faBars)

const props = defineProps<{
  data: {
    department: {}
    families: {
      id: number
      name: string
      slug: string
      image: ImageTS
      code: string
    }[]
    routes: {
      detach_family: routeType
    }
  }
}>()

// Loading state
const isLoadingDelete = ref<string[]>([])

const onDetachFamily = (slug: string) => {
  router.delete(
    route(props.data.routes.detach_family.name, {
      ...props.data.routes.detach_family.parameters,
      family: slug
    }),
    {
      onStart: () => {
        isLoadingDelete.value.push(slug)
      },
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

// Toggle tampilan
const viewMode = ref<'grid' | 'list'>('grid')
console.log(props.data.families)
</script>

<template>
  <div>
    <div
      v-if="data.families?.length"
      class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8"
    >
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8">
        <h2 class="text-2xl font-bold text-gray-800">
          {{ trans("Family list") }} ({{ data.families.length }})
        </h2>
        <div class="flex gap-2">
          <Button
            @click="viewMode = 'grid'"
            :type="viewMode != 'grid' ? 'tertiary' : 'secondary'"
            size="xs"
            :icon="faThLarge"
            label="Grid"
            :key="viewMode"
          >
          </Button>
           
          <Button
            @click="viewMode = 'list'"
            :type="viewMode != 'list' ? 'tertiary' : 'secondary'"
            size="xs"
             :key="viewMode"
            :icon="faBars"
            label="list"
          >
          </Button>
        </div>
      </div>

      <!-- Grid View -->
      <div
        v-if="viewMode === 'grid'"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8"
      >
        <div
          v-for="family in data.families"
          :key="family.id"
          class="bg-white p-4 rounded-2xl shadow hover:shadow-md transition-all duration-200 flex flex-col"
        >
          <div class="relative w-full overflow-hidden rounded-xl aspect-[4/3]">
            <Image
              :src="family.image"
              :alt="family.name"
              class="object-cover w-full h-full"
              imageCover
            />
          </div>
          <div class="mt-4">
            <h3 class="text-lg font-semibold text-gray-900 truncate">
              {{ family.name }}
            </h3>
            <p class="text-sm text-gray-500">{{ family.code }}</p>
          </div>
          <div class="mt-auto pt-4">
            <Button
              @click="onDetachFamily(family.slug)"
              label="Unlink"
              type="negative"
              icon="fal fa-unlink"
              :loading="isLoadingDelete.includes(family.slug)"
              full
            />
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="space-y-4">
        <div
          v-for="family in data.families"
          :key="family.id"
          class="bg-white p-4 rounded-xl border shadow-sm hover:shadow-md transition-all flex items-center gap-4"
        >
          <Image
            :src="family.image"
            :alt="family.name"
            class="w-14 h-14 rounded-xl object-cover flex-shrink-0"
            imageCover
          />
          <div class="flex-1 min-w-0">
            <h3 class="text-base font-semibold text-gray-900 truncate">
              {{ family.name }}
            </h3>
            <p class="text-sm text-gray-500 truncate">{{ family.code }}</p>
          </div>
          <div>
            <Button
              @click="onDetachFamily(family.slug)"
              label="Unlink"
              type="negative"
              icon="fal fa-unlink"
              :loading="isLoadingDelete.includes(family.slug)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="mx-auto max-w-2xl px-4 py-20 text-center">
      <EmptyState
        :data="{
          title: 'No families',
          description: 'This subdepartment has no families'
        }"
      />
    </div>
  </div>
</template>

