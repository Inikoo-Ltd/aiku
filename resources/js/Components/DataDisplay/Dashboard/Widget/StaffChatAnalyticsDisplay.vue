<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { faArrowRight } from '@fal'
import MiniBar from './MiniBar.vue'

library.add(faArrowRight)

type UserStat = { username: string; messages: number; conversations: number }
type PairStat = { conversation_id: number; members: string; messages: number }

const props = defineProps<{
    widget?: {
        days: number
        messages: number
        users: number
        conversations: number
        media_messages: number
        context_messages: number
        translated: number
        reactions: number
        unread_conversations: number
        top_users: UserStat[]
        top_pairs: PairStat[]
    } | null
}>()

const maxUserMessages = computed(() => Math.max(0, ...(props.widget?.top_users.map(u => u.messages) ?? [])))
const maxPairMessages = computed(() => Math.max(0, ...(props.widget?.top_pairs.map(p => p.messages) ?? [])))
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-lg font-semibold">
                {{ ctrans("Staff chat") }}
                <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
            </h3>
            <Link
                :href="route('grp.sysadmin.staff_chat.index')"
                class="text-xs text-indigo-600 hover:underline whitespace-nowrap"
            >
                {{ ctrans("Per-user & per-conversation stats") }}
                <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
            </Link>
        </div>

        <template v-if="widget && widget.messages">
            <div class="flex flex-wrap gap-x-10 gap-y-3 mb-4">
                <div>
                    <p class="text-4xl font-bold text-indigo-600">{{ widget.messages.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Messages") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-sky-600">{{ widget.users }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("People chatting") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-violet-600">{{ widget.conversations }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Conversations") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold text-emerald-600">{{ widget.context_messages.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("On orders / delivery notes") }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 mb-4">
                <span>{{ ctrans("Images") }}: <b class="text-gray-700">{{ widget.media_messages }}</b></span>
                <span>{{ ctrans("Translated") }}: <b class="text-gray-700">{{ widget.translated }}</b></span>
                <span>{{ ctrans("Reactions") }}: <b class="text-gray-700">{{ widget.reactions }}</b></span>
                <span>{{ ctrans("Unread conversations") }}: <b :class="widget.unread_conversations ? 'text-amber-600' : 'text-gray-700'">{{ widget.unread_conversations }}</b></span>
            </div>

            <div class="grid grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Most chatty") }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="user in widget.top_users"
                            :key="user.username"
                            :href="`${route('grp.sysadmin.staff_chat.index')}?filter[global]=${encodeURIComponent(user.username)}`"
                            class="block py-1 hover:bg-slate-50"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">{{ user.username }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ user.messages }}<span class="text-gray-400 font-normal"> / {{ user.conversations }} {{ ctrans("chats") }}</span></span>
                            </div>
                            <MiniBar :value="user.messages" :max="maxUserMessages" color="bg-indigo-400" />
                        </Link>
                        <p v-if="!widget.top_users.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1" />{{ ctrans("Pairs who chat most") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="pair in widget.top_pairs" :key="pair.conversation_id" class="py-1">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">{{ pair.members }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ pair.messages }}</span>
                            </div>
                            <MiniBar :value="pair.messages" :max="maxPairMessages" color="bg-sky-400" />
                        </div>
                        <p v-if="!widget.top_pairs.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No staff chat activity recorded yet") }}</p>
    </div>
</template>
