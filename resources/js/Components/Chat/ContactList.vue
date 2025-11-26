<script setup lang="ts">
import { ref, inject } from "vue"
import Button from "../Elements/Buttons/Button.vue"
import { faPaperPlane } from "@fas"
import { trans } from "laravel-vue-i18n"

const layout: any = inject("layout", {})

// Dummy contact list
const contacts = ref([
  {
    id: 1,
    name: "John Doe",
    avatar: "https://i.pravatar.cc/100?img=1",
    lastMessage: "Hey, how are you?",
    unread: 2,
  },
  {
    id: 2,
    name: "Sarah Quinn",
    avatar: "https://i.pravatar.cc/100?img=2",
    lastMessage: "Meeting at 2 PM, okay?",
    unread: 0,
  },
  {
    id: 3,
    name: "Michael Chen",
    avatar: "https://i.pravatar.cc/100?img=3",
    lastMessage: "Thanks for the update!",
    unread: 1,
  },
  {
    id: 4,
    name: "Lisa Park",
    avatar: "https://i.pravatar.cc/100?img=4",
    lastMessage: "Let's work on the new feature.",
    unread: 0,
  }
])

// Chat messages (optional)
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
  <div class="w-full h-full flex flex-col border bg-white">

    <!-- Header -->
    <div class="px-4 py-3 border-b font-semibold text-gray-700">
      Contacts
    </div>

    <!-- Contact List -->
    <div class="flex-1 overflow-y-auto">
      <div
        v-for="c in contacts"
        :key="c.id"
        class="flex items-center gap-4 px-4 py-3 border-b hover:bg-gray-50 cursor-pointer"
      >
        <img
          :src="c.avatar"
          class="w-12 h-12 rounded-full object-cover"
        />

        <div class="flex-1">
          <div class="font-semibold text-gray-800">
            {{ c.name }}
          </div>
          <div class="text-sm text-gray-500 truncate">
            {{ c.lastMessage }}
          </div>
        </div>

        <div v-if="c.unread" class="px-2 py-1 bg-red-500 text-white text-xs rounded-full">
          {{ c.unread }}
        </div>
      </div>
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
