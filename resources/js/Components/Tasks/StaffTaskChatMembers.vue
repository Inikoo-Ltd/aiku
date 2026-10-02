<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

defineProps<{
    members: {
        id: number
        name: string
        avatar: any
        role: string | null
        presence: {
            isOnline: boolean
            page: { label?: string; url?: string } | null
            lastActiveLabel: string | null
        }
    }[]
    myId: number | null
}>()
</script>

<template>
    <ul class="divide-y divide-gray-100">
        <li v-for="member in members" :key="member.id" class="flex items-start gap-2.5 px-3 py-2.5">
            <span class="relative">
                <TicketUserAvatar :name="member.name" :avatar="member.avatar" />
                <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-2 ring-white" :class="member.presence.isOnline ? 'bg-green-500' : 'bg-gray-300'" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5">
                    <span class="truncate text-sm font-medium text-gray-900">{{ member.name }}</span>
                    <span v-if="member.id === myId" class="text-xs text-gray-400">{{ ctrans("me") }}</span>
                </div>
                <div v-if="member.role" class="text-[11px] text-gray-500">{{ member.role }}</div>
                <div class="mt-0.5 text-xs">
                    <template v-if="member.presence.isOnline">
                        <a v-if="member.presence.page?.url" :href="member.presence.page.url" class="block truncate text-[--app-accent-strong] hover:underline" :title="member.presence.page.label">
                            {{ member.presence.page.label || member.presence.page.url }}
                        </a>
                        <span v-else class="text-green-600">{{ ctrans("Online") }}</span>
                    </template>
                    <span v-else-if="member.presence.lastActiveLabel" class="text-gray-400">{{ ctrans("Last seen :when", { when: member.presence.lastActiveLabel }) }}</span>
                    <span v-else class="text-gray-400">{{ ctrans("Offline") }}</span>
                </div>
            </div>
        </li>
    </ul>
</template>
