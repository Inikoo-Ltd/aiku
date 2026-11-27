<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faStar } from "@fal"
import { faStar as faStarSolid } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"

library.add(faPencil, faStar, faStarSolid)

const props = defineProps<{
    label: string
    href?: string
    icon?: string
    avatar?: string | null
    groupSize?: number
    unread?: number
    isActive?: boolean
    isMuted?: boolean
    isStarred?: boolean
    hasDraft?: boolean
    compact?: boolean
    iconOnly?: boolean
    noStar?: boolean
}>()

const emit = defineEmits<{ select: []; toggleStar: [] }>()

const unreadText = () => ((props.unread ?? 0) > 99 ? "99+" : String(props.unread))
const initials = () => props.label.replace(/[^a-z0-9]/gi, "").slice(0, 2).toUpperCase()
</script>

<template>
    <component
        v-if="compact"
        :is="href ? Link : 'button'"
        v-bind="href ? { href, preserveState: true, preserveScroll: true } : { type: 'button' }"
        class="relative mx-auto flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
        :class="isActive ? 'ring-2 ring-[var(--desk-strong)]' : 'hover:ring-2 hover:ring-[var(--desk-chip-strong)]'"
        :aria-label="label"
        v-tooltip.right="label"
        @click="!href && emit('select')">
        <span v-if="groupSize" class="flex h-full w-full items-center justify-center rounded-md bg-[var(--desk-chip-strong)] text-xs font-bold text-[var(--desk-strong)]">{{ groupSize }}</span>
        <span v-else-if="icon === undefined" class="h-full w-full overflow-hidden rounded-md bg-[var(--desk-chip-strong)]">
            <Image v-if="avatar" :src="avatar" :alt="label" image-cover />
            <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-sm" fixed-width aria-hidden="true" />
        </span>
        <span v-else-if="iconOnly" class="flex h-full w-full items-center justify-center rounded-md text-[15px]" :class="isActive ? 'bg-[var(--desk-active)] text-[var(--desk-active-text)]' : 'hover:bg-[var(--desk-hover)]'">
            <FontAwesomeIcon :icon="icon" fixed-width aria-hidden="true" />
        </span>
        <span v-else class="flex h-full w-full items-center justify-center rounded-md bg-[var(--desk-chip)] text-xs font-bold text-[var(--desk-strong)]" :class="isMuted && 'opacity-70'">{{ initials() }}</span>
        <span
            v-if="unread && !isActive"
            class="absolute -right-1.5 -top-1.5 min-w-[18px] rounded-full border-2 border-[var(--desk-sidebar)] bg-[var(--desk-badge)] px-1 text-center text-[10px] font-bold leading-[14px] text-white"
            :aria-label="ctrans(':count unread', { count: unreadText() })">{{ unreadText() }}</span>
    </component>
    <div v-else class="group/row relative mx-2">
        <component
            :is="href ? Link : 'button'"
            v-bind="href ? { href, preserveState: true, preserveScroll: true } : { type: 'button' }"
            class="flex h-7 w-full items-center gap-x-2 rounded-md pl-4 pr-8 text-left"
            :class="isActive ? 'bg-[var(--desk-active)] text-[var(--desk-active-text)]' : unread ? 'font-bold text-[var(--desk-strong)] hover:bg-[var(--desk-hover)]' : [isMuted && 'opacity-70', 'hover:bg-[var(--desk-hover)]']"
            @click="!href && emit('select')">
            <span v-if="groupSize" class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-[var(--desk-chip-strong)] text-[11px] font-bold text-[var(--desk-strong)]">{{ groupSize }}</span>
            <span v-else-if="icon === undefined" class="h-5 w-5 shrink-0 overflow-hidden rounded bg-[var(--desk-chip-strong)]">
                <Image v-if="avatar" :src="avatar" :alt="label" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-xs" fixed-width aria-hidden="true" />
            </span>
            <FontAwesomeIcon v-else :icon="icon" class="shrink-0 text-[13px]" fixed-width aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">{{ label }}</span>
            <FontAwesomeIcon v-if="hasDraft" icon="fal fa-pencil" class="shrink-0 text-[11px] opacity-70 group-hover/row:hidden" fixed-width v-tooltip="ctrans('Draft')" :aria-label="ctrans('Draft')" />
            <span
                v-if="unread && !isActive"
                class="shrink-0 rounded-full bg-[var(--desk-badge)] px-2 text-xs font-bold leading-[18px] text-white group-hover/row:hidden"
                :aria-label="ctrans(':count unread', { count: unreadText() })">{{ unreadText() }}</span>
        </component>
        <button
            v-if="!noStar"
            type="button"
            class="absolute right-1 top-1 flex h-5 w-6 items-center justify-center rounded opacity-0 hover:bg-[var(--desk-chip-strong)] focus:opacity-100 group-hover/row:opacity-100"
            :aria-label="isStarred ? ctrans('Remove from Starred') : ctrans('Add to Starred')"
            v-tooltip="isStarred ? ctrans('Remove from Starred') : ctrans('Add to Starred')"
            @click="emit('toggleStar')">
            <FontAwesomeIcon :icon="isStarred ? 'fas fa-star' : 'fal fa-star'" class="text-xs" fixed-width aria-hidden="true" />
        </button>
    </div>
</template>
