<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faUnlink, faThLarge, faBars, faSeedling, faCheck } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { ref } from 'vue'
import EmptyState from '../Utils/EmptyState.vue'
import Image from '../Image.vue'
import { Image as ImageTS } from '@/types/Image'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Icon from "@/Components/Icon.vue"
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

library.add(faUnlink, faThLarge, faBars, faSeedling, faCheck)

const props = defineProps<{
  data: {
    department: {}
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
      detach_family: routeType
    }
  }
}>()

const isLoadingDelete = ref<string[]>([])

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
</script>

<template>
  <div class="w-1/3 px-4 py-8 sm:px-6 lg:px-8">
    <div class="bg-white rounded-2xl shadow p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6 border-b pb-3">
        <h2 class="text-xl font-bold text-gray-800">
          {{ trans("Family list") }} ({{ data.families.length }})
        </h2>
      </div>

      <!-- Disclosure List -->
      <div v-if="data.families?.length" class="space-y-3 max-h-[500px] overflow-y-auto pr-1">
        <Disclosure v-for="family in data.families" :key="family.id" v-slot="{ open }">
          <div class="border border-gray-100 rounded-lg overflow-hidden">
            <DisclosureButton
              class="w-full flex items-center gap-4 p-3 bg-gray-50 hover:bg-gray-100 transition text-left"
            >
              <Image :src="family.image" :alt="family.name" class="w-8 h-8 rounded-lg object-cover flex-shrink-0" imageCover />
              <div class="flex-1 min-w-0">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                  <span class="block truncate w-40 sm:w-48 md:w-56">
                    {{ family.name }}
                  </span>
                  <Icon v-if="family.state" :data="family.state" :title="family.state.label" />
                </h3>
                <p class="text-sm text-gray-500 truncate">{{ family.code }}</p>
              </div>
              <Button
                @click.stop="onDetachFamily(family.slug)"
                label="Unlink"
                type="negative"
                icon="fal fa-unlink"
                size="xs"
                :loading="isLoadingDelete.includes(family.slug)"
              />
            </DisclosureButton>

            <DisclosurePanel class="bg-white border-t border-gray-100 p-4 space-y-3">
              <div v-if="family.products?.length">
                <div
                  v-for="product in family.products"
                  :key="product.id"
                  class="flex items-center gap-4 py-1"
                >
                  <Image
                    :src="product.images.source"
                    :alt="product.name"
                    class="w-10 h-10 rounded-lg object-cover"
                    imageCover
                  />
                  <div class="text-sm text-gray-700 font-medium">{{ product.name }}</div>
                </div>
              </div>
              <div v-else class="text-sm text-gray-400 italic">
                No products available under this family.
              </div>
            </DisclosurePanel>
          </div>
        </Disclosure>
      </div>

      <div v-else class="mx-auto max-w-2xl px-4 py-20 text-center">
        <EmptyState :data="{
          title: 'No families',
          description: 'This subdepartment has no families'
        }" />
      </div>
    </div>
  </div>
</template>
