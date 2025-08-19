<script setup lang="ts">
import { faCube, faLink, faImage } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, onMounted, computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import { useFormatTime } from "@/Composables/useFormatTime";
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faImage)

const props = defineProps<{
  modelValue?: any
  webpageData?: any
  blockData?: Object
  indexBlock: number
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  (e: "autoSave"): void
}>()

const titleTextarea = ref<HTMLTextAreaElement | null>(null)

const autoResize = (event: Event) => {
  const target = event.target as HTMLTextAreaElement
  target.style.height = 'auto'
  target.style.height = target.scrollHeight + 'px'
}

onMounted(() => {
  if (titleTextarea.value) {
    titleTextarea.value.style.height = 'auto'
    titleTextarea.value.style.height = titleTextarea.value.scrollHeight + 'px'
  }
})

const displayDate = computed(() => {
  return props.modelValue.published_date ? props.modelValue.published_date : new Date();
});

</script>


<template>
  <article class="max-w-3xl mx-auto px-4 py-8 text-gray-800">
    <!-- Title as textarea -->
    <div  class="text-4xl font-bold tracking-tight mb-3 leading-snug text-gray-900" :style="getStyles(modelValue?.properties, screenType)">
        <Editor v-model="modelValue.title" @update:modelValue="() => emits('autoSave')" class="mb-6"
          placeholder="Blog Title" :uploadImageRoute="{
            name: webpageData.images_upload_route.name,
            parameters: {
              ...webpageData.images_upload_route.parameters,
              modelHasWebBlocks: blockData?.id,
            }
          }" />
    </div>

    <!-- Date -->
    <div class="text-sm text-gray-500 mb-6">
      {{ useFormatTime(displayDate) }}
    </div>

    <!-- Hero Image or Placeholder -->
    <div
      class="w-full mb-8 rounded-xl shadow-md overflow-hidden aspect-[2/1] bg-gray-100 flex items-center justify-center">
      <Image v-if="modelValue?.image?.source" :src="modelValue?.image?.source" :alt="modelValue?.image.alt"
        :imageCover="true" class="w-full h-full object-cover" />
      <FontAwesomeIcon v-else :icon="['fas', 'image']" class="text-gray-400 text-6xl" />
    </div>

    <!-- Content Editor -->
    <div :style="getStyles(modelValue?.properties, screenType)">
      <Editor v-model="modelValue.content" @update:modelValue="() => emits('autoSave')" class="mb-6"
        placeholder="Blog content" :uploadImageRoute="{
          name: webpageData.images_upload_route.name,
          parameters: {
            ...webpageData.images_upload_route.parameters,
            modelHasWebBlocks: blockData?.id,
          }
        }" />

    </div>

  </article>
</template>


<style scoped>
textarea::placeholder {
  font-weight: 500;
  opacity: 0.5;
}

.prose img {
  border-radius: 0.5rem;
}
</style>
