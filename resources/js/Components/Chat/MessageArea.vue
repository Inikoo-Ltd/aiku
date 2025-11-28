<script setup lang="ts">
import { ref, inject } from "vue"
import Button from "../Elements/Buttons/Button.vue"
import { faPaperPlane } from "@fas"
import { trans } from "laravel-vue-i18n";

const layout: any = inject("layout", {});
const messages = ref([
  { from: "bot", text: "Hello! How can I help you today?" },
  { from: "user", text: "Hi, I want to ask something." }
])

const input = ref("")

const sendMessage = () => {
  const text = input.value.trim()
  if (!text) return

  messages.value.push({ from: "user", text })
  input.value = ""
}
</script>

<template>
  <div class="flex flex-col h-full"> <!-- FIX: mengikuti tinggi parent -->

    <!-- Header -->
    <div class="px-4 py-3 border-b border-gray-200 font-semibold text-gray-700 shadow">
      {{ trans('Chat Support') }}
    </div>

    <!-- Messages Area -->
    <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50"> <!-- FIX: expand -->
      <div 
        v-for="(msg, index) in messages" 
        :key="index"
        class="flex"
        :class="msg.from === 'user' ? 'justify-end' : 'justify-start'"
      >
        <div 
          class="px-3 py-2 rounded-lg max-w-[75%] text-sm"
          :class="
            msg.from === 'user' 
              ? 'primary-chat-bubble rounded-br-none' 
              : 'bg-gray-200 text-gray-800 rounded-bl-none'
          "
        >
          {{ msg.text }}
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="p-3 border-t border-gray-200 flex items-center gap-2">
      <input
        v-model="input"
        @keyup.enter="sendMessage"
        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring focus:ring-blue-200 outline-none"
        placeholder="Type a message..."
      />
      <Button :icon="faPaperPlane" />
    </div>

  </div>
</template>

<style scoped>
.primary-chat-bubble {
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
