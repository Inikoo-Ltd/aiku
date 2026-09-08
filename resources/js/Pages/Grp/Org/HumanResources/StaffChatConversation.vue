<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Aug 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Image from "@/Common/Components/Image.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    pageHead: any
    title: string
    conversation: {
        ulid: string
        type: string
        context: string | null
        members: string
        created_at: string
        last_message_at: string | null
    }
    messages: {
        id: number
        user_name: string
        body: string
        image: any
        gif_url: string | null
        reactions: Record<string, number[]>
        created_at: string
    }[]
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 max-w-4xl">
        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 mb-4">
            <span>{{ ctrans("Who") }}: <b class="text-gray-700">{{ conversation.members }}</b></span>
            <span>{{ ctrans("Type") }}: <b class="text-gray-700">{{ conversation.type }}</b></span>
            <span v-if="conversation.context">{{ ctrans("Context") }}: <b class="text-gray-700">{{ conversation.context }}</b></span>
            <span>{{ ctrans("Started") }}: <b class="text-gray-700">{{ useFormatTime(conversation.created_at, { formatTime: 'hms', keepTimezone: true }) }}</b></span>
            <span>{{ ctrans("Messages") }}: <b class="text-gray-700">{{ messages.length }}</b></span>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-300 divide-y divide-gray-100">
            <div v-for="message in messages" :key="message.id" class="px-4 py-2 flex gap-3 text-sm">
                <span class="shrink-0 w-36 text-xs text-gray-400 whitespace-nowrap tabular-nums">{{ useFormatTime(message.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
                <span class="shrink-0 w-28 font-medium truncate">{{ message.user_name }}</span>
                <div class="min-w-0 flex-1">
                    <img v-if="message.gif_url" :src="message.gif_url" class="max-h-40 rounded" alt="" />
                    <p v-else class="whitespace-pre-wrap break-words">{{ message.body }}</p>
                    <Image v-if="message.image" :src="message.image" class="mt-1 max-h-60 rounded" />
                    <span v-if="Object.keys(message.reactions ?? {}).length" class="text-xs text-gray-400">
                        <span v-for="(users, emoji) in message.reactions" :key="emoji" class="mr-2">{{ emoji }} {{ users.length }}</span>
                    </span>
                </div>
            </div>
            <p v-if="!messages.length" class="px-4 py-6 text-sm text-gray-400">{{ ctrans("No messages") }}</p>
        </div>
    </div>
</template>
