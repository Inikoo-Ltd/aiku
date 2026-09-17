<!--
 Author Louis Perez
 Created on 17-09-2026-13h-23m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBookOpen, faExternalLink } from "@fal"

library.add(faBookOpen, faExternalLink)

const props = withDefaults(defineProps<{
    ticketId: number
    compact?: boolean
}>(), { compact: false })

type RelatedTicket = { id: number; reference: string; subject: string; status_label: string; status_icon: any; type_icon: any }
type Article = { title: string; summary: string; url: string; source: "customer" | "help" }

const relatedTickets = ref<RelatedTicket[]>([])
const articles = ref<Article[]>([])
const isLoading = ref(true)
const hasFailed = ref(false)

const loadRecommendations = async (ticketId: number) => {
    isLoading.value = true
    hasFailed.value = false
    try {
        const { data } = await axios.get(route("grp.json.ticket.recommendations", ticketId))
        if (ticketId !== props.ticketId) return
        relatedTickets.value = data.related_tickets ?? []
        articles.value = data.articles ?? []
    } catch {
        if (ticketId === props.ticketId) hasFailed.value = true
    } finally {
        if (ticketId === props.ticketId) isLoading.value = false
    }
}

watch(
    () => props.ticketId,
    (ticketId) => {
        if (ticketId) loadRecommendations(ticketId)
    },
    { immediate: true }
)

const shown = <T,>(items: T[]) => (props.compact ? items.slice(0, 3) : items)
</script>

<template>
    <div class="space-y-3 text-sm">
        <div v-if="isLoading" class="space-y-2">
            <div v-for="placeholder in 3" :key="placeholder" class="h-7 animate-pulse rounded bg-gray-100" />
        </div>
        <p v-else-if="hasFailed" class="text-xs text-gray-400">{{ trans("Suggestions could not be loaded") }}</p>
        <template v-else>
            <div>
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Related tickets") }}</p>
                <ul v-if="relatedTickets.length" class="space-y-0.5">
                    <li v-for="related in shown(relatedTickets)" :key="related.id">
                        <Link :href="route('grp.tickets.show', related.reference)" class="flex items-center gap-2 rounded px-1.5 py-1 transition duration-200 hover:bg-gray-50">
                            <Icon v-if="related.type_icon" :data="related.type_icon" class="text-gray-400" />
                            <span class="shrink-0 font-medium text-indigo-600">{{ related.reference }}</span>
                            <span class="min-w-0 flex-1 truncate text-gray-700">{{ related.subject }}</span>
                            <span v-tooltip="related.status_label" class="shrink-0"><Icon :data="related.status_icon" /></span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="px-1.5 text-xs text-gray-400">{{ trans("No similar tickets found") }}</p>
            </div>
            <div>
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ trans("Knowledge base") }}</p>
                <ul v-if="articles.length" class="space-y-0.5">
                    <li v-for="article in shown(articles)" :key="article.url + article.title">
                        <a :href="article.url" target="_blank" rel="noopener" class="group flex items-start gap-2 rounded px-1.5 py-1 transition duration-200 hover:bg-gray-50">
                            <FontAwesomeIcon icon="fal fa-book-open" fixed-width class="mt-0.5 shrink-0 text-gray-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-gray-800 transition duration-200 group-hover:text-indigo-600">{{ article.title }}</span>
                                <span v-if="!compact && article.summary" class="line-clamp-2 block text-xs text-gray-500">{{ article.summary }}</span>
                            </span>
                            <FontAwesomeIcon icon="fal fa-external-link" fixed-width class="mt-0.5 shrink-0 text-gray-300 transition duration-200 group-hover:text-gray-500" />
                        </a>
                    </li>
                </ul>
                <p v-else class="px-1.5 text-xs text-gray-400">{{ trans("No matching articles") }}</p>
            </div>
        </template>
    </div>
</template>
