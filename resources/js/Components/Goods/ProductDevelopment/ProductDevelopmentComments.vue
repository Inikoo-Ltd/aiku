<!--
  - Author Louis Perez
  - Created on 16-10-2026-17h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import ProductDevelopmentCommentComposer, { type Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"

interface Comment {
    id: number
    body: string
    user_id: number | null
    user: string | null
    username: string | null
    avatar: Record<string, string> | null
    created_at: string | null
    images?: Record<string, string>[]
    attachments?: any[]
    replies?: Comment[]
}

const props = defineProps<{
    data: {
        threads: Comment[]
        mentionables: Mentionable[]
        route: routeType | null
    }
    order?: "newest" | "oldest"
}>()

const threads = computed(() => {
    const list = [...(props.data?.threads ?? [])].sort((a, b) => b.id - a.id)

    return props.order === "oldest" ? list.reverse() : list
})

const replyingTo = ref<number | null>(null)

const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM, HH:mm" }) : ""

</script>

<template>
    <div v-if="data" class="space-y-3 px-4 py-3">
        <ProductDevelopmentCommentComposer v-if="data.route" :route="data.route" :mentionables="data.mentionables" />

        <ol class="space-y-3">
            <li v-for="thread in threads" :key="thread.id" class="space-y-2">
                <template v-for="comment in [thread, ...(thread.replies ?? [])]" :key="comment.id">
                    <div class="flex items-start gap-2" :class="comment.id !== thread.id ? 'ml-8' : ''">
                        <TicketUserHoverCard
                            class="mt-0.5 shrink-0"
                            :name="comment.user"
                            :avatar="comment.avatar"
                            :username="comment.username"
                            :messageUserId="comment.user_id"
                            :canMention="false"
                            size="sm"
                            avatarOnly
                        />
                        <div class="min-w-0 flex-1">
                            <div class="text-xs">
                                <span class="font-medium text-gray-800">{{ comment.user }}</span>
                                <span class="ml-1.5 tabular-nums text-gray-400">{{ formatWhen(comment.created_at) }}</span>
                            </div>
                            <TicketBody :text="comment.body" :images="comment.images" :attachments="comment.attachments" class="text-sm" />
                        </div>
                    </div>
                </template>
                <div v-if="data.route" class="ml-8">
                    <button v-if="replyingTo !== thread.id" type="button" class="text-xs text-gray-500 hover:text-[--app-accent]" @click="replyingTo = thread.id">
                        {{ ctrans("Reply") }}
                    </button>
                    <ProductDevelopmentCommentComposer
                        v-else
                        :route="data.route"
                        :mentionables="data.mentionables"
                        :parentId="thread.id"
                        :placeholder="ctrans('Write a reply')"
                        :rows="2"
                        @posted="replyingTo = null"
                    />
                </div>
            </li>
        </ol>

        <p v-if="!data.threads.length" class="py-2 text-center text-xs text-gray-400">{{ ctrans("No comments yet") }}</p>
    </div>
</template>
