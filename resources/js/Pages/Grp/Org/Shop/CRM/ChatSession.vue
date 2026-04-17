<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faComments, faCircle, faUser, faRobot, faHeadset, faCog, faPaperclip, faImage, faDownload } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { PageHeadingTypes } from '@/types/PageHeading'
library.add(faComments, faCircle, faUser, faRobot, faHeadset, faCog, faPaperclip, faImage, faDownload)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    chatSession: {
        ulid: string
        status: string
        contact_name: string
        assigned_agent: string | null
        created_at: { formatted: string; diff: string } | null
        closed_at: { formatted: string; diff: string } | null
        priority: string
        is_guest: boolean
    }
    messages: Array<{
        id: number
        message_text: string | null
        message_type: string
        sender_type: string
        is_agent: boolean
        is_guest: boolean
        is_user: boolean
        is_system: boolean
        is_ai: boolean
        is_read: boolean
        media_url: string | null
        file_name: string | null
        file_size: number | null
        file_mime: string | null
        download_route: { url: string } | null
        created_at: string
    }>
}>()

const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    waiting: 'bg-yellow-100 text-yellow-700',
    resolved: 'bg-blue-100 text-blue-700',
    transferred: 'bg-purple-100 text-purple-700',
    closed: 'bg-gray-100 text-gray-600',
}

function isFromAgent(msg: typeof props.messages[0]): boolean {
    return msg.is_agent
}

function senderLabel(msg: typeof props.messages[0]): string {
    if (msg.is_agent) return 'Agent'
    if (msg.is_ai) return 'AI'
    if (msg.is_system) return 'System'
    if (msg.is_user || msg.is_guest) return props.chatSession.contact_name || 'Customer'
    return 'Unknown'
}

function formatTimestamp(raw: string): string {
    return new Date(raw).toLocaleString()
}

function formatFileSize(bytes: number | null): string {
    if (!bytes) return ''
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-6 py-4 max-w-4xl mx-auto">
        <div class="bg-white shadow rounded-lg p-4 mb-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <div class="flex items-center gap-x-1.5">
                <span class="text-gray-500">Status:</span>
                <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                    :class="statusColors[chatSession.status] ?? 'bg-gray-100 text-gray-600'"
                >
                    {{ chatSession.status }}
                </span>
            </div>
            <div v-if="chatSession.assigned_agent" class="flex items-center gap-x-1.5">
                <FontAwesomeIcon icon="fal fa-headset" class="text-gray-400" fixed-width />
                <span class="text-gray-500">Agent:</span>
                <span class="font-medium">{{ chatSession.assigned_agent }}</span>
            </div>
            <div v-if="chatSession.created_at" class="flex items-center gap-x-1.5">
                <span class="text-gray-500">Started:</span>
                <span>{{ chatSession.created_at.formatted }}</span>
            </div>
            <div v-if="chatSession.closed_at" class="flex items-center gap-x-1.5">
                <span class="text-gray-500">Closed:</span>
                <span>{{ chatSession.closed_at.formatted }}</span>
            </div>
        </div>

        <div class="flex flex-col gap-y-3">
            <template v-for="msg in messages" :key="msg.id">
                <div
                    v-if="!msg.is_system && !msg.is_ai"
                    :class="['flex', isFromAgent(msg) ? 'justify-end' : 'justify-start']"
                >
                    <div
                        :class="[
                            'max-w-xs md:max-w-md lg:max-w-lg rounded-2xl px-4 py-2.5 shadow-sm',
                            isFromAgent(msg)
                                ? 'bg-indigo-500 text-white rounded-br-sm'
                                : 'bg-white text-gray-800 rounded-bl-sm border border-gray-100'
                        ]"
                    >
                        <div class="text-[11px] font-semibold mb-0.5 opacity-70">
                            {{ senderLabel(msg) }}
                        </div>

                        <template v-if="msg.message_type === 'image' && msg.media_url">
                            <img
                                :src="msg.media_url"
                                class="rounded-lg max-w-full max-h-64 object-contain"
                                alt="image"
                            />
                        </template>

                        <template v-else-if="msg.message_type === 'file' && msg.download_route">
                            <a
                                :href="msg.download_route.url"
                                target="_blank"
                                :class="[
                                    'flex items-center gap-x-2 text-sm underline',
                                    isFromAgent(msg) ? 'text-white/90' : 'text-indigo-600'
                                ]"
                            >
                                <FontAwesomeIcon icon="fal fa-paperclip" />
                                <span>{{ msg.file_name || 'Download file' }}</span>
                                <span v-if="msg.file_size" class="text-xs opacity-60">
                                    ({{ formatFileSize(msg.file_size) }})
                                </span>
                            </a>
                        </template>

                        <template v-else>
                            <p class="text-sm whitespace-pre-wrap break-words">
                                {{ msg.message_text || '—' }}
                            </p>
                        </template>

                        <div
                            :class="[
                                'text-[10px] mt-1 text-right',
                                isFromAgent(msg) ? 'text-white/60' : 'text-gray-400'
                            ]"
                        >
                            {{ formatTimestamp(msg.created_at) }}
                        </div>
                    </div>
                </div>

                <div v-else class="flex justify-center">
                    <span class="text-xs text-gray-400 bg-gray-50 rounded-full px-3 py-1 border border-gray-100">
                        <FontAwesomeIcon v-if="msg.is_ai" icon="fal fa-robot" class="mr-1" />
                        <FontAwesomeIcon v-else icon="fal fa-cog" class="mr-1" />
                        {{ msg.message_text }}
                    </span>
                </div>
            </template>

            <div v-if="!messages.length" class="text-center text-gray-400 py-12">
                No messages in this session.
            </div>
        </div>
    </div>
</template>
