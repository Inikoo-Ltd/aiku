<script setup lang="ts">
import { faCube, faLink, faImage } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, onMounted } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

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
</script>


<template>
  <article class="max-w-3xl mx-auto px-4 py-8 text-gray-800">
    <!-- Title as textarea -->
    <div class="mb-4">
      <textarea
        v-model="modelValue.title"
        @input="autoResize($event)"
        @change="emits('autoSave')"
        placeholder="Blog Title"
        class="resize-none overflow-hidden w-full bg-transparent border-none p-0 m-0 text-4xl font-extrabold leading-tight text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0"
        rows="1"
        ref="titleTextarea"
      ></textarea>
    </div>

    <!-- Date -->
    <div class="text-sm text-gray-500 mb-6">
      <time :datetime="modelValue.date">
        {{ new Date(modelValue.date).toLocaleDateString('id-ID', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        }) }}
      </time>
    </div>

    <!-- Hero Image or Placeholder -->
    <div class="w-full mb-8 rounded-xl shadow-md overflow-hidden aspect-[2/1] bg-gray-100 flex items-center justify-center">
      <img
        v-if="modelValue.Image"
        :src="modelValue.Image"
        alt="Gambar Sampul"
        class="w-full h-full object-cover"
      />
      <FontAwesomeIcon
        v-else
        :icon="['fas', 'image']"
        class="text-gray-400 text-6xl"
      />
    </div>

    <!-- Content Editor -->
    <Editor
      v-model="modelValue.content"
      @update:modelValue="() => emits('autoSave')"
      class="mb-6"
      placeholder="Blog content"
      :uploadImageRoute="{
        name: webpageData.images_upload_route.name,
        parameters: {
          ...webpageData.images_upload_route.parameters,
          modelHasWebBlocks: blockData?.id,
        }
      }"
    />
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

