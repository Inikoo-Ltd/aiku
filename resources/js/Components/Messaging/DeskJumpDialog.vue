<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHashtag, faSearch, faUser, faUsers } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import Image from "@/Common/Components/Image.vue"
import type { StaffCoworker } from "@/Stores/staff-messaging"

library.add(faHashtag, faSearch, faUser, faUsers)

export interface DeskJumpTarget {
    key: string
    kind: "channel" | "conversation" | "coworker"
    label: string
    avatar?: string | null
    isGroup?: boolean
    unread?: number
    archived?: boolean
    channelId?: number
    ulid?: string
    coworker?: StaffCoworker
}

const props = defineProps<{ targets: DeskJumpTarget[] }>()
const visible = defineModel<boolean>("visible", { required: true })
const emit = defineEmits<{ (e: "select", target: DeskJumpTarget): void }>()

const query = ref("")
const activeIndex = ref(0)
const coworkers = ref<StaffCoworker[]>([])
const inputEl = ref<{ $el: HTMLInputElement } | null>(null)
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const normalise = (text: string) => text.toLowerCase().replace(/^[#@]/, "").trim()

const matches = computed<DeskJumpTarget[]>(() => {
    const q = normalise(query.value)
    const known = props.targets.filter((target) => !target.archived || q)
    if (!q) {
        return [...known.filter((target) => target.unread), ...known.filter((target) => !target.unread)].slice(0, 12)
    }
    const found = known
        .filter((target) => normalise(target.label).includes(q))
        .sort((a, b) => Number(!normalise(a.label).startsWith(q)) - Number(!normalise(b.label).startsWith(q)))
    const dmNames = new Set(found.filter((target) => target.kind === "conversation").map((target) => target.label.toLowerCase()))
    const people = coworkers.value
        .filter((coworker) => !dmNames.has(coworker.name.toLowerCase()))
        .map<DeskJumpTarget>((coworker) => ({ key: "cw-" + coworker.id, kind: "coworker", label: coworker.name, avatar: coworker.avatar, coworker }))
    return [...found, ...people].slice(0, 20)
})

watch(matches, () => {
    activeIndex.value = 0
})

watch(query, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout)
    if (value.trim().length < 2) {
        coworkers.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        try {
            const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: normalise(value) } })
            coworkers.value = data.data
        } catch {
            coworkers.value = []
        }
    }, 250)
})

watch(visible, (isOpen) => {
    if (!isOpen) return
    query.value = ""
    coworkers.value = []
    nextTick(() => inputEl.value?.$el?.focus())
})

const choose = (target: DeskJumpTarget | undefined) => {
    if (!target) return
    visible.value = false
    emit("select", target)
}

const onKeydown = (event: KeyboardEvent) => {
    const count = matches.value.length
    if (event.key === "ArrowDown" && count) {
        event.preventDefault()
        activeIndex.value = (activeIndex.value + 1) % count
    } else if (event.key === "ArrowUp" && count) {
        event.preventDefault()
        activeIndex.value = (activeIndex.value - 1 + count) % count
    } else if (event.key === "Enter") {
        event.preventDefault()
        choose(matches.value[activeIndex.value])
    }
}
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        dismissable-mask
        :show-header="false"
        position="top"
        :style="{ width: '38rem', marginTop: '12vh' }"
        :breakpoints="{ '640px': '94vw' }"
        :pt="{ content: { class: '!p-0 overflow-hidden rounded-lg' } }">
        <div class="font-desk text-[15px] text-[#1d1c1d]">
            <div class="flex items-center gap-x-2 border-b border-gray-200 px-4 py-3">
                <FontAwesomeIcon icon="fal fa-search" class="text-gray-500" fixed-width aria-hidden="true" />
                <InputText
                    ref="inputEl"
                    v-model="query"
                    :placeholder="ctrans('Jump to a channel or person…')"
                    class="flex-1 !border-0 !p-0 !text-[17px] !shadow-none !ring-0"
                    :aria-label="ctrans('Jump to a channel or person…')"
                    @keydown="onKeydown" />
            </div>
            <div class="max-h-[50vh] overflow-y-auto py-2" role="listbox">
                <div v-if="!query.trim()" class="px-4 pb-1 pt-1 text-[13px] font-bold text-gray-500">{{ ctrans("Recent") }}</div>
                <button
                    v-for="(target, index) in matches"
                    :key="target.key"
                    type="button"
                    role="option"
                    :aria-selected="index === activeIndex"
                    class="flex h-9 w-full items-center gap-x-2.5 px-4 text-left"
                    :class="index === activeIndex ? 'bg-[#f2e9f2] text-[#39063a]' : ''"
                    @mousemove="activeIndex = index"
                    @click="choose(target)">
                    <FontAwesomeIcon v-if="target.kind === 'channel'" icon="fal fa-hashtag" class="shrink-0" fixed-width aria-hidden="true" />
                    <span v-else-if="target.isGroup" class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-gray-300 text-[11px] font-bold text-gray-700">
                        <FontAwesomeIcon icon="fal fa-users" fixed-width aria-hidden="true" />
                    </span>
                    <span v-else class="h-5 w-5 shrink-0 overflow-hidden rounded bg-gray-300">
                        <Image v-if="target.avatar" :src="target.avatar" :alt="target.label" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-xs text-gray-500" fixed-width aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1 truncate" :class="target.unread ? 'font-bold' : ''">{{ target.label }}</span>
                    <span v-if="target.kind === 'coworker'" class="shrink-0 text-[13px] text-gray-500">{{ ctrans("Start a chat") }}</span>
                    <span v-else-if="target.archived" class="shrink-0 text-[13px] text-gray-500">{{ ctrans("Archived") }}</span>
                </button>
                <div v-if="!matches.length" class="px-4 py-6 text-center text-sm text-gray-500">{{ ctrans("No matches") }}</div>
            </div>
            <div class="flex items-center gap-x-4 border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-500">
                <span><kbd class="font-sans font-semibold">↑↓</kbd> {{ ctrans("to navigate") }}</span>
                <span><kbd class="font-sans font-semibold">↵</kbd> {{ ctrans("to open") }}</span>
                <span><kbd class="font-sans font-semibold">Esc</kbd> {{ ctrans("to close") }}</span>
            </div>
        </div>
    </Dialog>
</template>
