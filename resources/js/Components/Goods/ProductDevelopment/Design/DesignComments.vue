<!--
  - Author Louis Perez
  - Created on 17-10-2026-14h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { markupColour } from "@/Components/Goods/ProductDevelopment/Design/boxNet"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import ProductDevelopmentCommentComposer, { type Mentionable } from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCommentComposer.vue"

export interface DesignComment {
    id: number
    body: string
    user_id: number | null
    user: string | null
    username: string | null
    avatar: Record<string, string> | null
    created_at: string | null
    images?: Record<string, string>[]
    attachments?: any[]
    pin: { x: number, y: number, number: number, page?: number } | null
    stroke: { page: number, points: [number, number][], number: number } | null
    version: number | null
    status: "open" | "resolved" | null
    resolved_by: string | null
    resolved_in: number | null
    replies?: DesignComment[]
}

const props = defineProps<{
    data: { threads: DesignComment[], mentionables: Mentionable[], route: routeType }
    version: number
    target: Record<string, number>
    statusRoute: string
    only?: string[]
    pendingMarkup: ({ type: "pin", x: number, y: number, page?: number } | { type: "stroke", page: number, points: [number, number][] }) | null
    pendingNumber: number
    activeId: number | null
}>()

const emit = defineEmits<{
    cancelPin: []
    posted: []
    focus: [id: number]
    changed: []
}>()

const only = computed(() => props.only ?? ["comments", "versions"])

const threads = computed(() => [...props.data.threads]
    .filter((thread) => thread.version === props.version)
    .sort((a, b) => markNumber(a) - markNumber(b) || a.id - b.id))

function markNumber(thread: DesignComment): number {
    return thread.pin?.number ?? thread.stroke?.number ?? 999
}

const extra = computed(() => {
    const markup = props.pendingMarkup
    if (!markup) {
        return { ...props.target }
    }
    if (markup.type === "stroke") {
        return { ...props.target, stroke: { page: markup.page, points: markup.points } }
    }
    return { ...props.target, pin: { x: markup.x, y: markup.y, ...(markup.page ? { page: markup.page } : {}) } }
})

const replyingTo = ref<number | null>(null)
const updating = ref<number | null>(null)

function setStatus(comment: DesignComment, status: "open" | "resolved") {
    router.patch(route(props.statusRoute, { productDevelopmentComment: comment.id }), { status }, {
        preserveScroll: true,
        only: only.value,
        onStart: () => updating.value = comment.id,
        onFinish: () => updating.value = null,
        onSuccess: () => emit("changed"),
    })
}

const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM, HH:mm" }) : ""
</script>

<template>
    <div class="space-y-3 px-4 py-3">
        <ol class="space-y-3">
            <li
                v-for="thread in threads"
                :key="thread.id"
                class="space-y-2 rounded-md p-1.5 transition-colors"
                :class="thread.id === activeId ? 'bg-[--app-accent-soft]' : ''"
                @click="emit('focus', thread.id)"
            >
                <template v-for="comment in [thread, ...(thread.replies ?? [])]" :key="comment.id">
                    <div class="flex items-start gap-2" :class="comment.id !== thread.id ? 'ml-8' : ''">
                        <span
                            v-if="comment.id === thread.id && (thread.pin || thread.stroke)"
                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="thread.status === 'resolved' ? 'opacity-50' : ''"
                            :style="{ backgroundColor: markupColour(markNumber(thread)), color: '#fff' }"
                        >{{ markNumber(thread) }}</span>
                        <TicketUserHoverCard
                            v-else
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
                                <span v-if="comment.id === thread.id && thread.version !== version" class="ml-1.5 text-gray-400">· {{ ctrans("on v:version", { version: String(thread.version) }) }}</span>
                            </div>
                            <TicketBody :text="comment.body" :images="comment.images" :attachments="comment.attachments" class="text-sm" />
                        </div>
                    </div>
                </template>
                <div class="ml-8 flex flex-wrap items-center gap-3 text-xs">
                    <span v-if="thread.status === 'resolved'" class="text-green-700">
                        {{ thread.resolved_in ? ctrans("Resolved in v:version", { version: String(thread.resolved_in) }) : ctrans("Resolved") }}
                    </span>
                    <span v-else class="text-amber-700">{{ ctrans("Open") }}</span>
                    <button type="button" class="text-gray-500 hover:text-[--app-accent] disabled:opacity-50" :disabled="updating === thread.id" @click.stop="setStatus(thread, thread.status === 'resolved' ? 'open' : 'resolved')">
                        {{ thread.status === "resolved" ? ctrans("Reopen") : ctrans("Resolve") }}
                    </button>
                    <button v-if="replyingTo !== thread.id" type="button" class="text-gray-500 hover:text-[--app-accent]" @click.stop="replyingTo = thread.id">{{ ctrans("Reply") }}</button>
                </div>
                <div v-if="replyingTo === thread.id" class="ml-8" @click.stop>
                    <ProductDevelopmentCommentComposer
                        :route="data.route"
                        :mentionables="data.mentionables"
                        :parentId="thread.id"
                        :placeholder="ctrans('Write a reply')"
                        :rows="2"
                        :only="only"
                        @posted="() => { replyingTo = null; emit('changed') }"
                    />
                </div>
            </li>
        </ol>
        <p v-if="!threads.length" class="py-2 text-center text-xs text-gray-400">{{ ctrans("No comments yet") }}</p>

        <div class="space-y-2 border-t border-gray-100 pt-3">
            <div v-if="pendingMarkup" class="flex items-center justify-between gap-2 text-xs">
                <span class="flex items-center gap-2 text-gray-700">
                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold text-white" :style="{ backgroundColor: markupColour(pendingNumber) }">{{ pendingNumber }}</span>
                    {{ pendingMarkup.type === "stroke" ? ctrans("Comment on this drawing") : ctrans("Comment on this point") }}
                </span>
                <Button type="negative" size="xs" :label="ctrans('Cancel')" @click="emit('cancelPin')" />
            </div>
            <span v-else class="block text-xs text-gray-500">{{ ctrans("Reply or add a general comment, or pin or draw on the design") }}</span>
            <ProductDevelopmentCommentComposer
                :key="pendingMarkup ? `markup-${JSON.stringify(pendingMarkup).length}-${pendingMarkup.type}` : 'general'"
                :route="data.route"
                :mentionables="data.mentionables"
                :placeholder="ctrans('Type @ to mention someone')"
                :rows="3"
                :only="only"
                :extra="extra as any"
                @posted="emit('posted')"
            />
        </div>
    </div>
</template>
