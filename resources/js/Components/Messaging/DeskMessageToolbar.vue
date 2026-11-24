<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Menu } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDots, faEllipsisV, faSmilePlus } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"

library.add(faCommentDots, faEllipsisV, faSmilePlus)

export interface DeskToolbarMenuItem {
    label: string
    icon: string
    danger?: boolean
    command: () => void
}

const DESK_QUICK_REACTIONS = ["✅", "👀", "🙌"]

const props = withDefaults(defineProps<{
    canReact?: boolean
    mine?: string[]
    replyLabel?: string | null
    menu?: DeskToolbarMenuItem[]
}>(), { canReact: false, mine: () => [], replyLabel: null, menu: () => [] })

const emit = defineEmits<{
    (e: "react", emoji: string): void
    (e: "addReaction", event: Event): void
    (e: "reply"): void
}>()

const moreMenu = ref()
const menuModel = () => props.menu.map((item) => ({ label: item.label, icon: item.icon, danger: item.danger, command: item.command }))
</script>

<template>
    <div class="flex items-center rounded-lg border border-[#1d1c1d21] bg-white p-0.5 text-[#616061] shadow-[0_0_0_1px_rgba(29,28,29,0.04),0_4px_12px_0_rgba(0,0,0,0.08)]" role="toolbar" :aria-label="ctrans('Message actions')">
        <template v-if="canReact">
            <button
                v-for="emoji in DESK_QUICK_REACTIONS"
                :key="emoji"
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded text-base leading-none hover:bg-[#f8f8f8]"
                :class="mine.includes(emoji) && 'bg-[#e8f5fa]'"
                :aria-label="ctrans('React with :emoji', { emoji })"
                v-tooltip="ctrans('React with :emoji', { emoji })"
                @click="emit('react', emoji)">
                {{ emoji }}
            </button>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded hover:bg-[#f8f8f8] hover:text-[#1d1c1d]"
                :aria-label="ctrans('Add reaction')"
                v-tooltip="ctrans('Add reaction')"
                @click="emit('addReaction', $event)">
                <FontAwesomeIcon icon="fal fa-smile-plus" fixed-width aria-hidden="true" />
            </button>
        </template>
        <button
            v-if="replyLabel"
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded hover:bg-[#f8f8f8] hover:text-[#1d1c1d]"
            :aria-label="replyLabel"
            v-tooltip="replyLabel"
            @click="emit('reply')">
            <FontAwesomeIcon icon="fal fa-comment-dots" fixed-width aria-hidden="true" />
        </button>
        <button
            v-if="menu.length"
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded hover:bg-[#f8f8f8] hover:text-[#1d1c1d]"
            :aria-label="ctrans('More actions')"
            v-tooltip="ctrans('More actions')"
            aria-haspopup="true"
            @click="moreMenu?.toggle($event)">
            <FontAwesomeIcon icon="fal fa-ellipsis-v" fixed-width aria-hidden="true" />
        </button>
        <Menu ref="moreMenu" :model="menuModel()" popup class="font-desk">
            <template #item="{ item, props: itemProps }">
                <a v-bind="itemProps.action" class="flex items-center gap-x-2 px-3 py-1.5 text-[15px]" :class="item.danger ? 'text-[#e01e5a]' : 'text-[#1d1c1d]'">
                    <FontAwesomeIcon :icon="item.icon" fixed-width aria-hidden="true" />
                    <span>{{ item.label }}</span>
                </a>
            </template>
        </Menu>
    </div>
</template>
