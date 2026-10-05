<script setup lang="ts">
import { computed, ref } from 'vue'
import { faCopy, faExternalLink } from '@fal'
import { faEye, faEyeSlash } from '@far'
import { faTimesCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { routeType } from '@/types/route'
import Image from "@common/Components/Image.vue"
import { ctrans } from '@/Composables/useTrans'

library.add(faCopy, faExternalLink, faEye, faEyeSlash, faTimesCircle, faSpinnerThird)

const props = defineProps<{
  modelValue: string | number | null | undefined
  fetchRoute: routeType
}>()


const emits = defineEmits<{
  (e: 'update:modelValue', value: number | string | null): void
}>()

const _pureMultiselectInfiniteScroll = ref(null)
const value = computed({
  get: () => props.modelValue,
  set: v => emits('update:modelValue', { id: v?.id, name: v?.name, slug: v?.slug })
})

const getBannerRoute = (routeSuffix: string, extraParams: Record<string, string> = {}): string => {
  const routeParams = route().params

  if (routeParams.fulfilment) {
    return route(`grp.org.fulfilments.show.web.banners.${routeSuffix}`, {
      organisation: routeParams.organisation,
      fulfilment: routeParams.fulfilment,
      website: routeParams.website,
      ...extraParams,
    })
  }

  return route(`grp.org.shops.show.web.banners.${routeSuffix}`, {
    organisation: routeParams.organisation,
    shop: routeParams.shop,
    website: routeParams.website,
    ...extraParams,
  })
}

const selectedBannerSlug = computed(() => (props.modelValue as { slug?: string } | null | undefined)?.slug ?? null)

const bannerLink = computed(() => {
  if (selectedBannerSlug.value) {
    return {
      url: getBannerRoute('workshop', { banner: selectedBannerSlug.value }),
      label: ctrans('Edit this banner in Banner Workshop'),
    }
  }

  return {
    url: getBannerRoute('index'),
    label: ctrans('Go to Banners to create a new banner'),
  }
})

</script>

<template>
  <div>
  <PureMultiselectInfiniteScroll 
      v-model="value" 
      :fetch-route="fetchRoute" 
      :object="true" 
      labelProp="name"
      value-prop="id"
      ref="_pureMultiselectInfiniteScroll"
     :optionFunc="(item) => item.state == 'live'">

    <!-- selected label -->
    <template #singlelabel="{ value }">
      <div class="w-full flex items-center pl-3 pr-2 truncate text-sm">
        <span class="truncate font-medium text-gray-800">
          {{ value?.name ?? value?.slug }}
        </span>
      </div>
    </template>

    <!-- option -->
    <template #option="{ option, isSelected }">
      <div :key="option.slug"
        class="group w-full bg-white rounded-xl border border-gray-200 overflow-hidden cursor-pointer transition-all duration-200 hover:shadow-lg hover:-translate-y-[2px]"
        :class="isSelected ? 'ring-2 ring-primary/40 border-primary/40' : ''">
        <!-- image -->
        <div class="h-36 bg-gray-50 flex items-center justify-center overflow-hidden">
          <Image v-if="option.image_thumbnail" :src="option.image_thumbnail"
            class="object-contain w-full h-full transition-transform duration-300 group-hover:scale-105" />
          <div v-else class="text-xs text-gray-400">
            No image
          </div>
        </div>

        <!-- content -->
        <div class="px-3 py-2">
          <h3 class="text-sm font-semibold text-gray-900 truncate">
            {{ option.name }}
          </h3>

          <p v-if="option.slug" class="text-xs text-gray-400 truncate mt-0.5">
            {{ option.slug }}
          </p>
        </div>
      </div>
    </template>

  </PureMultiselectInfiniteScroll>

  <a :href="bannerLink.url" target="_blank" rel="noopener"
    class="mt-1.5 inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
    <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
    {{ bannerLink.label }}
  </a>
  </div>
</template>