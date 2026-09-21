<!--
 Author Louis Perez
 Created on 18-09-2026-13h-20m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faComments } from "@fal"
import { useTicketChatPanel } from "@/Composables/useTicketChatPanel"

library.add(faComments)

const props = defineProps<{
    source: {
        channel_icon?: { icon: any; class?: string } | null
        channel_label: string
        contact?: string | null
        reference?: string | null
        url?: string | null
        has_conversation?: boolean
    }
    ticketId?: number | string | null
}>()

// These same details become the conversation's header once it is opened, so the panel gives
// them up rather than saying everything twice.
const { isOpen: isConversationOpen } = useTicketChatPanel(props.ticketId ?? '')
</script>

<template>
    <div v-if="!isConversationOpen" class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm">
        <div class="flex items-center gap-2">
            <FontAwesomeIcon v-if="source.channel_icon" :icon="source.channel_icon.icon" :class="source.channel_icon.class" fixed-width aria-hidden="true" />
            <span class="font-medium text-gray-800">{{ source.channel_label }}</span>
        </div>
        <dl class="mt-2 space-y-1 text-gray-600">
            <div v-if="source.contact" class="flex justify-between gap-2"><dt>{{ trans("Contact") }}</dt><dd class="truncate">{{ source.contact }}</dd></div>
            <div v-if="source.reference" class="flex justify-between gap-2"><dt>{{ trans("Reference") }}</dt><dd class="font-mono text-xs">{{ source.reference }}</dd></div>
        </dl>
        <a v-if="source.url" :href="source.url" class="mt-2 inline-flex items-center gap-1 text-blue-600 hover:underline">
            <FontAwesomeIcon :icon="['fal', 'comments']" fixed-width aria-hidden="true" />
            {{ trans("Open conversation") }}
        </a>
    </div>
</template>
