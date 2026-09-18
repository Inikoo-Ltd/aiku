<!--
 Author Louis Perez
 Created on 18-09-2026-13h-19m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUser, faUsers, faCube, faChevronDown, faQuestionCircle, faBug, faLightbulb, faLevelUp, faTasks, faVial, faBooks, faDatabase, faSearch } from "@fal"

library.add(faUser, faUsers, faCube, faChevronDown, faQuestionCircle, faBug, faLightbulb, faLevelUp, faTasks, faVial, faBooks, faDatabase, faSearch)

const props = withDefaults(defineProps<{
    ticket: any
    storageKey: string
    defaultOpen?: boolean
}>(), { defaultOpen: true })

const kindIcons: Record<string, string> = {
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    escalation: "fal fa-level-up",
    task: "fal fa-tasks",
    qa: "fal fa-vial",
    documentation: "fal fa-books",
    data_integrity: "fal fa-database",
    support: "fal fa-search",
}

const readPanelState = (): boolean => {
    try {
        const stored = localStorage.getItem(props.storageKey)
        return stored === null ? props.defaultOpen : stored !== "closed"
    } catch {
        return props.defaultOpen
    }
}

const isOpen = ref(readPanelState())

const toggle = () => {
    isOpen.value = !isOpen.value
    try {
        localStorage.setItem(props.storageKey, isOpen.value ? "open" : "closed")
    } catch {
        return
    }
}

const summaryPeople = computed(() => (props.ticket.collaborators ?? []) as { id: number; name: string; short: string; avatar?: any }[])
</script>

<template>
    <aside class="bg-white rounded-lg border border-gray-300 text-sm">
        <button type="button" class="flex w-full items-start justify-between gap-3 p-4 text-left transition duration-200 hover:bg-gray-50" @click="toggle">
            <span v-if="isOpen" class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Control panel") }}</span>
            <span v-if="!isOpen" class="flex min-w-0 flex-col gap-1.5">
                <span class="flex items-center gap-2">
                    <TicketUserAvatar v-if="ticket.assignee" :name="ticket.assignee" :avatar="ticket.assignee_avatar" size="sm" />
                    <span v-else class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-user" fixed-width class="text-xs" />
                    </span>
                    <span :class="ticket.assignee ? 'font-medium text-gray-800' : 'text-gray-400'">{{ ticket.assignee_short || trans("Unassigned") }}</span>
                </span>
                <span
                    v-if="summaryPeople.length"
                    v-tooltip="{ content: summaryPeople.map((person) => person.name).join(', '), delay: 0 }"
                    class="flex items-center gap-1.5">
                    <FontAwesomeIcon icon="fal fa-users" fixed-width class="text-xs text-gray-400" />
                    <span class="flex -space-x-1.5">
                        <TicketUserAvatar v-for="person in summaryPeople.slice(0, 3)" :key="person.id" :name="person.name" :avatar="person.avatar" size="xs" class="ring-2 ring-white" />
                    </span>
                    <span v-if="summaryPeople.length > 3" class="text-xs text-gray-500">{{ trans("+:count others", { count: String(summaryPeople.length - 3) }) }}</span>
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span v-if="!isOpen" class="flex flex-col items-end gap-1.5">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            <Icon :data="ticket.status_icon" />{{ ticket.status_label }}
                        </span>
                        <span v-if="ticket.qa_status" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            <Icon :data="ticket.qa_status_icon" />{{ ticket.qa_status_label }}
                        </span>
                    </span>
                    <span v-if="ticket.kind || ticket.module_label" class="flex items-center gap-1.5">
                        <span v-if="ticket.kind" v-tooltip="ticket.kind_label" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            <FontAwesomeIcon :icon="kindIcons[ticket.kind] ?? 'fal fa-question-circle'" fixed-width />
                        </span>
                        <span v-if="ticket.module_label" v-tooltip="ticket.module_label" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                            <FontAwesomeIcon icon="fal fa-cube" fixed-width />
                        </span>
                    </span>
                </span>
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isOpen && '-rotate-90'" />
            </span>
        </button>
        <div v-show="isOpen" class="space-y-4 border-t border-gray-200 p-4">
            <slot />
        </div>
    </aside>
</template>
