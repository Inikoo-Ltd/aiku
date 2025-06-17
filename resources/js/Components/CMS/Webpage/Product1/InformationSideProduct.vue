<script setup lang="ts">
import { faCube, faLink, faChevronDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCube, faLink)

type InfoBlock = {
  id: number
  title: string
  text: string
}

const props = defineProps<{
  informations: InfoBlock[]
}>()

const openDisclosureId = ref<number | null>(null)

function toggleDisclosure(index: number) {
  openDisclosureId.value = openDisclosureId.value === index ? null : index
}

console.log('sdsd',props)
</script>

<template>
  <div class="w-full">
    <div class="space-y-3">
      <template v-for="(content, index) in informations" :key="content.id">
        <div class="relative">
          <button
            @click="toggleDisclosure(index)"
            class="w-full  mb-1 border-gray-400 font-bold text-gray-800 py-1 flex justify-between items-center cursor-pointer focus:outline-none"
            :aria-expanded="openDisclosureId === index"
            :aria-controls="'disclosure-' + content.id"
          >
            {{ content.title }}
            <FontAwesomeIcon
              :icon="faChevronDown"
              class="text-sm text-gray-500 transform transition-transform duration-200"
              :class="{ 'rotate-180': openDisclosureId === index }"
            />
          </button>
          <transition name="fade">
            <div
              v-if="openDisclosureId === index"
              :id="'disclosure-' + content.id"
              class="text-sm text-gray-600"
            >
              {{ content.text }}
            </div>
          </transition>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
