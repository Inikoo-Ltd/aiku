<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEye, faPen } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { chatColleaguesOn } from "@/Composables/useChatDrafting"
import { formatChatAge } from "@/Composables/chatTime"

/**
 * HELP-4896: a full-width strip under the conversation header while a colleague has it open, red
 * while they are writing a reply, amber while they are only reading, so two people do not answer
 * the same customer. Orange when nobody is on it now but a colleague left a reply unsent.
 */
const props = defineProps<{
    sessionUlid: string | null | undefined
    unsentDraft?: { name: string, updated_at?: string | null } | null
}>()

const colleagues = computed(() => chatColleaguesOn(props.sessionUlid))
const lead = computed(() => colleagues.value[0])
const others = computed(() => colleagues.value.slice(1))
const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join("").toUpperCase()
</script>

<template>
    <div v-if="!lead && unsentDraft" role="status"
        class="flex items-center gap-3 bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-orange-700 ring-2 ring-orange-200">
            {{ initials(unsentDraft.name) }}
        </span>
        <FontAwesomeIcon :icon="faPen" class="shrink-0" fixed-width aria-hidden="true" />
        <span class="min-w-0">
            <template v-if="unsentDraft.updated_at">{{ ctrans(":name started a reply :age ago and has not sent it", { name: unsentDraft.name, age: formatChatAge(+new Date(unsentDraft.updated_at)) }) }}</template>
            <template v-else>{{ ctrans(":name started a reply and has not sent it yet", { name: unsentDraft.name }) }}</template>
        </span>
    </div>
    <div v-else-if="lead" role="status" aria-live="polite"
        class="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold"
        :class="lead.mode === 'writing' ? 'bg-red-600 text-white' : 'bg-amber-300 text-gray-900'">
        <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2"
            :class="lead.mode === 'writing' ? 'bg-white text-red-700 ring-red-300' : 'bg-gray-900 text-amber-300 ring-amber-100'">
            {{ initials(lead.name) }}
            <span class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                    :class="lead.mode === 'writing' ? 'bg-white' : 'bg-gray-900'" />
                <span class="relative inline-flex h-3.5 w-3.5 rounded-full ring-2"
                    :class="lead.mode === 'writing' ? 'bg-white ring-red-600' : 'bg-gray-900 ring-amber-300'" />
            </span>
        </span>
        <FontAwesomeIcon :icon="lead.mode === 'writing' ? faPen : faEye" class="shrink-0" fixed-width aria-hidden="true" />
        <span class="min-w-0">
            <template v-if="lead.mode === 'writing'">{{ ctrans(":name is writing a reply to this customer right now", { name: lead.name }) }}</template>
            <template v-else>{{ ctrans(":name has this conversation open", { name: lead.name }) }}</template>
            <span v-if="others.length" class="ml-1 font-normal opacity-90">
                {{ ctrans("and :names", { names: others.map((colleague) => colleague.name).join(", ") }) }}
            </span>
        </span>
    </div>
</template>
