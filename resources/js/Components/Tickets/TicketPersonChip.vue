<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faSpinner } from "@fal"
library.add(faTimes, faSpinner)

withDefaults(defineProps<{
    name: string
    short?: string | null
    avatar?: any
    removable?: boolean
    removing?: boolean
}>(), { short: null, avatar: null, removable: false, removing: false })

const emit = defineEmits<{
    remove: []
}>()
</script>

<template>
    <span v-tooltip="name" class="group/chip inline-flex items-center gap-1.5 rounded-full bg-gray-100 py-1 pl-1 pr-2.5 text-xs text-gray-700" :class="removing && 'opacity-60'">
        <span class="relative inline-flex shrink-0">
            <TicketUserAvatar :name="name" :avatar="avatar" size="xs" />
            <button
                v-if="removable || removing"
                type="button"
                :disabled="removing"
                :aria-label="ctrans('Remove :name', { name })"
                v-tooltip="ctrans('Remove :name', { name })"
                class="absolute inset-0 flex items-center justify-center rounded-full bg-red-500/90 text-[10px] text-white opacity-0 transition duration-200 focus:opacity-100 group-hover/chip:opacity-100 disabled:cursor-wait [@media(hover:none)]:opacity-100"
                :class="removing && '!opacity-100'"
                @click.stop="emit('remove')">
                <FontAwesomeIcon :icon="removing ? 'fal fa-spinner' : 'fal fa-times'" :spin="removing" fixed-width aria-hidden="true" />
            </button>
        </span>
        {{ short ?? name.split(" ")[0] }}
    </span>
</template>
