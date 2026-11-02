<!--
  - Author Louis Perez
  - Created on 26-10-2026-15h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import ProductDevelopmentCommentComposer, { type Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinner } from "@fal"
library.add(faSpinner)

interface ThreadComment {
    id: number
    body: string
    user_id: number | null
    user: string | null
    username: string | null
    role: string | null
    avatar: Record<string, string> | null
    created_at: string | null
    images?: Record<string, string>[]
    attachments?: any[]
    review: "approve" | "request_changes" | null
    replies?: ThreadComment[]
}

const props = defineProps<{
    commentsRoute: routeType
    sampleId: number
    count: number
}>()

const thread = ref<{ threads: ThreadComment[], mentionables: Mentionable[], route: routeType } | null>(null)
const loading = ref(false)
const replyingTo = ref<number | null>(null)

const comments = computed(() => [...(thread.value?.threads ?? [])].sort((a, b) => a.id - b.id))
const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy, HH:mm" }) : ""

async function load() {
    loading.value = true
    try {
        const { data } = await axios.get(route(props.commentsRoute.name, props.commentsRoute.parameters))
        thread.value = data
    } finally {
        loading.value = false
    }
}

watch(() => [props.sampleId, props.count], load, { immediate: true })
</script>

<template>
    <div class="space-y-3">
        <p v-if="!thread && loading" class="flex items-center gap-2 text-xs text-gray-400">
            <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" /> {{ ctrans("Loading…") }}
        </p>
        <template v-else-if="thread">
            <ol class="space-y-3">
                <li v-for="item in comments" :key="item.id" class="space-y-2">
                    <div v-for="entry in [item, ...(item.replies ?? [])]" :key="entry.id" class="flex items-start gap-2" :class="entry.id !== item.id ? 'ml-8' : ''">
                        <TicketUserHoverCard class="mt-0.5 shrink-0" :name="entry.user" :avatar="entry.avatar" :username="entry.username" :messageUserId="entry.user_id" :canMention="false" size="sm" avatarOnly />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-1.5 text-xs">
                                <span class="font-medium text-gray-800">{{ entry.user }}</span>
                                <span v-if="entry.role" class="text-gray-500">{{ entry.role }}</span>
                                <span class="tabular-nums text-gray-400">{{ formatWhen(entry.created_at) }}</span>
                                <span v-if="entry.review" class="rounded-full px-1.5 py-0.5 text-[11px] font-medium" :class="entry.review === 'approve' ? 'bg-green-50 text-green-700' : 'bg-orange-50 text-orange-700'">
                                    {{ entry.review === "approve" ? ctrans("Approved") : ctrans("Changes required") }}
                                </span>
                            </div>
                            <TicketBody :text="entry.body" :images="entry.images" :attachments="entry.attachments" class="text-sm" />
                        </div>
                    </div>
                    <div class="ml-8">
                        <button v-if="replyingTo !== item.id" type="button" class="text-xs text-gray-500 hover:text-[--app-accent]" @click="replyingTo = item.id">{{ ctrans("Reply") }}</button>
                        <ProductDevelopmentCommentComposer v-else :route="thread.route" :mentionables="thread.mentionables" :parentId="item.id" :only="['formulation']" :placeholder="ctrans('Write a reply')" :rows="2" @posted="replyingTo = null" />
                    </div>
                </li>
            </ol>
            <p v-if="!comments.length" class="text-xs text-gray-400">{{ ctrans("No comments yet") }}</p>
            <ProductDevelopmentCommentComposer :route="thread.route" :mentionables="thread.mentionables" :extra="{ sample_id: sampleId }" :only="['formulation']" :rows="2" />
        </template>
    </div>
</template>
