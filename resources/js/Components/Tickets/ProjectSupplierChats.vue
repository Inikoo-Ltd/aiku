<!--
  - Author: Artha <artha@aw-advantage.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronRight, faPaperclip, faComments } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useStaffMessaging, type StaffMessage } from "@/Stores/staff-messaging"

library.add(faChevronDown, faChevronRight, faPaperclip, faComments)

export interface ProjectSupplierChat {
    ulid: string
    name: string
    type: "single" | "group"
    organisation_code: string
    milestone: string | null
    last_message_at: string | null
    messages: StaffMessage[]
}

defineProps<{
    chats: { conversations: ProjectSupplierChat[]; hidden: number }
}>()

const store = useStaffMessaging()
const openUlids = ref<string[]>([])

const toggle = (ulid: string) => {
    openUlids.value = openUlids.value.includes(ulid) ? openUlids.value.filter((open) => open !== ulid) : [...openUlids.value, ulid]
}
</script>

<template>
    <div class="space-y-3 p-4">
        <p v-if="!chats.conversations.length" class="text-sm text-gray-500">
            {{ ctrans("No supplier chats filed under this project yet. Open a supplier WeChat chat in the messaging panel and pick this project.") }}
        </p>

        <p v-if="chats.hidden" class="text-xs text-gray-500">
            {{ ctrans(":count more supplier chats are filed here from organisations whose procurement you cannot see.", { count: String(chats.hidden) }) }}
        </p>

        <section v-for="chat in chats.conversations" :key="chat.ulid" class="rounded-md border border-gray-200 bg-white">
            <div class="flex items-center gap-2 px-3 py-2">
                <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-left" :aria-expanded="openUlids.includes(chat.ulid)" @click="toggle(chat.ulid)">
                    <FontAwesomeIcon :icon="openUlids.includes(chat.ulid) ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="text-gray-400" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon :icon="chat.type === 'group' ? 'fal fa-comments' : 'fab fa-weixin'" class="text-green-600" fixed-width aria-hidden="true" />
                    <span class="truncate text-sm font-medium text-gray-900">{{ chat.name }}</span>
                    <span class="shrink-0 rounded bg-gray-100 px-1.5 text-xxs uppercase tracking-wide text-gray-500">{{ chat.organisation_code }}</span>
                    <span v-if="chat.milestone" class="shrink-0 truncate text-xs text-gray-500">· {{ chat.milestone }}</span>
                </button>
                <span v-if="chat.last_message_at" class="shrink-0 text-xs text-gray-400">{{ useFormatTime(chat.last_message_at, { formatTime: "hm" }) }}</span>
                <button
                    v-if="store.conversationByUlid(chat.ulid)"
                    type="button"
                    class="shrink-0 rounded px-2 py-0.5 text-xs text-[--app-accent-strong] hover:bg-[--app-accent-soft] focus:outline-none focus:ring-1 focus:ring-[--app-accent]"
                    @click="store.openConversation(chat.ulid)">
                    {{ ctrans("Open chat") }}
                </button>
            </div>

            <ol v-if="openUlids.includes(chat.ulid)" class="space-y-2 border-t border-gray-100 px-3 py-3">
                <li v-for="message in chat.messages" :key="message.id" class="flex flex-col" :class="message.is_from_supplier ? 'items-start' : 'items-end'">
                    <span class="text-xxs text-gray-400">{{ message.user_name }} · {{ useFormatTime(message.created_at, { formatTime: "hm" }) }}</span>
                    <div class="max-w-[80%] rounded-lg px-3 py-1.5 text-sm" :class="message.is_from_supplier ? 'bg-gray-100 text-gray-900' : 'bg-[--app-accent-soft] text-gray-900'">
                        <p class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                        <p v-for="attachment in message.attachments ?? []" :key="attachment.name" class="mt-1 flex items-center gap-1 text-xxs text-gray-500">
                            <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ attachment.name }}</span>
                        </p>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
