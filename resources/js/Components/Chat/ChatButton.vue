<script setup lang="ts">
import { ref, inject, onMounted, onBeforeUnmount } from "vue"
import MessageArea from "@/Components/Chat/MessageArea.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"

const layout: any = inject("layout", {})

const open = ref(false)
const buttonRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)

const toggle = () => {
  open.value = !open.value
}

const handleClickOutside = (e: MouseEvent) => {
  if (!open.value) return

  const target = e.target as Node

  const clickedOutside =
    panelRef.value &&
    !panelRef.value.contains(target) &&
    buttonRef.value &&
    !buttonRef.value.contains(target)

  if (clickedOutside) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener("mousedown", handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener("mousedown", handleClickOutside)
})
</script>

<template>
  <div>
    <!-- Floating Button -->
    <button
      ref="buttonRef"
      @click="toggle"
      class="fixed bottom-20 right-5 z-[60] flex items-center gap-2 px-4 py-4 rounded-xl shadow-lg buttonPrimary"
    >
      <FontAwesomeIcon :icon="faMessage" class="text-base" />
    </button>

    <!-- Chat Panel -->
    <transition
      enter-active-class="transition duration-150"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        ref="panelRef"
        class="fixed bottom-[9rem] right-5 z-[70] w-[350px] h-[350px] bg-white rounded-md overflow-hidden border"
      >
        <MessageArea />
      </div>
    </transition>
  </div>
</template>

<style scoped>
.buttonPrimary {
  background-color: v-bind('layout?.app?.theme[4]') !important;
  color: v-bind('layout?.app?.theme[5]') !important;
  border: v-bind('`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`');

  &:hover {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, black)`') !important;
  }

  &:focus {
    box-shadow: 0 0 0 2px v-bind('layout?.app?.theme[4]') !important;
  }
}
</style>
