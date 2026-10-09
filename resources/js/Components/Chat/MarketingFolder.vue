<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { formatChatTime } from "@/Composables/chatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAngleLeft } from "@fal"

export interface MarketingMailshot {
    id: number
    subject: string
    type: "newsletter" | "marketing"
    shop: string | null
    sent_at: string | null
}

const props = defineProps<{
    organisationSlug: string
    mailshots: MarketingMailshot[]
    readIds: number[]
    loading: boolean
    showShop: boolean
}>()

const emit = defineEmits<{ (e: "read", id: number): void }>()

const selected = ref<MarketingMailshot | null>(null)
const html = ref<string | null>(null)
const isLoadingHtml = ref(false)

const open = async (mailshot: MarketingMailshot) => {
    selected.value = mailshot
    html.value = null
    isLoadingHtml.value = true
    emit("read", mailshot.id)

    try {
        const response = await axios.get(route("grp.org.chat.marketing.show", [props.organisationSlug, mailshot.id]))
        if (selected.value?.id === mailshot.id) {
            html.value = response.data?.html ?? null
        }
    } finally {
        isLoadingHtml.value = false
    }
}
</script>

<template>
    <div class="flex flex-1 min-w-0">
        <div class="border-r border-gray-200 flex-col min-[1440px]:w-80 min-[1440px]:shrink-0 min-[1440px]:flex-none"
            :class="selected ? 'hidden min-[1440px]:flex' : 'flex flex-1 min-w-0'">
            <div class="px-3 py-1.5 border-b">
                <div class="text-sm font-semibold text-gray-800 mb-0.5">{{ ctrans("Marketing") }}</div>
                <div class="text-[11px] text-gray-500 truncate">
                    {{ ctrans("Newsletters and campaigns sent to customers in the last 30 days") }}
                </div>
            </div>
            <div class="flex-1 overflow-y-auto [scrollbar-width:thin]">
                <div v-if="loading && !mailshots.length" class="flex justify-center py-8">
                    <LoadingIcon />
                </div>
                <div v-else-if="!mailshots.length" class="py-8 text-center text-sm text-gray-400">
                    {{ ctrans("Nothing sent in the last 30 days") }}
                </div>
                <button v-for="mailshot in mailshots" :key="mailshot.id" type="button"
                    class="w-full text-left px-3 py-2 border-b border-gray-100 transition-colors"
                    :class="selected?.id === mailshot.id ? 'bg-[--app-accent-soft]' : 'hover:bg-gray-50'"
                    @click="open(mailshot)">
                    <div class="flex items-center gap-2">
                        <span v-if="!readIds.includes(mailshot.id)" class="h-2 w-2 shrink-0 rounded-full bg-[--app-accent]" />
                        <span class="flex-1 truncate text-sm"
                            :class="readIds.includes(mailshot.id) ? 'text-gray-600' : 'font-semibold text-gray-800'">
                            {{ mailshot.subject }}
                        </span>
                        <span class="shrink-0 text-[11px] text-gray-400">{{ mailshot.sent_at ? formatChatTime(mailshot.sent_at) : "" }}</span>
                    </div>
                    <div class="mt-0.5 text-[11px] text-gray-500 truncate">
                        {{ mailshot.type === "newsletter" ? ctrans("Newsletter") : ctrans("Campaign") }}<template v-if="showShop && mailshot.shop"> · {{ mailshot.shop }}</template>
                    </div>
                </button>
            </div>
        </div>

        <div class="flex-1 min-w-0 flex-col" :class="selected ? 'flex' : 'hidden min-[1440px]:flex'">
            <div v-if="!selected" class="h-full flex flex-col items-center justify-center gap-2 text-gray-400">
                <div class="text-4xl">📣</div>
                <div class="text-sm">{{ ctrans("Select an email to read what customers received") }}</div>
            </div>
            <template v-else>
                <div class="px-3 py-2 border-b flex items-center gap-2">
                    <button type="button" class="min-[1440px]:hidden text-gray-500" :aria-label="ctrans('Back')" @click="selected = null">
                        <FontAwesomeIcon :icon="faAngleLeft" fixed-width />
                    </button>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-800 truncate">{{ selected.subject }}</div>
                        <div class="text-[11px] text-gray-500">
                            {{ selected.shop }}<template v-if="selected.sent_at"> · {{ formatChatTime(selected.sent_at) }}</template>
                        </div>
                    </div>
                </div>
                <div v-if="isLoadingHtml" class="flex-1 flex justify-center items-center"><LoadingIcon /></div>
                <iframe v-else-if="html" :srcdoc="html" sandbox="allow-popups allow-popups-to-escape-sandbox" class="flex-1 w-full bg-white" />
                <div v-else class="flex-1 flex items-center justify-center text-sm text-gray-400">
                    {{ ctrans("This email has no content to show") }}
                </div>
            </template>
        </div>
    </div>
</template>
