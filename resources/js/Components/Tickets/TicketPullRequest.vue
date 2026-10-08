<!--
 Author Louis Perez
 Created on 30-09-2026
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faPencil, faSave, faSpinner, faTimes, faCodeBranch, faCodeCommit, faChevronDown } from "@fal"

library.add(faExternalLink, faPencil, faSave, faSpinner, faTimes, faCodeBranch, faCodeCommit, faChevronDown)

type RouteData = { name: string; parameters: Record<string, unknown> }

type PullRequest = {
    number: number
    title: string
    url: string
    repository: string
    state: "open" | "draft" | "merged" | "closed"
    state_label: string
    body: string | null
    author: { login: string | null; avatar: string | null; url: string | null }
    created_at: string | null
}

type Commit = {
    sha: string
    short_sha: string
    subject: string
    message: string
    author: string | null
    author_avatar: string | null
    date: string | null
    url: string
}

const props = defineProps<{
    ticket: {
        id: number
        pull_request_url: string | null
        commits?: { hash: string; subject: string; url?: string | null; version?: string | null; deployed_at?: string | null }[]
    }
    routes: { pull_request?: RouteData; pull_request_update?: RouteData }
    canEdit?: boolean
    compact?: boolean
}>()

const emit = defineEmits<{
    (e: "updated"): void
}>()

const stateClasses: Record<PullRequest["state"], string> = {
    open: "bg-green-100 text-green-700",
    draft: "bg-gray-100 text-gray-600",
    merged: "bg-purple-100 text-purple-700",
    closed: "bg-red-100 text-red-700",
}

const pullRequest = ref<PullRequest | null>(null)
const commits = ref<Commit[] | null>(null)
const fetchError = ref("")
const isFetching = ref(false)
let latestRequest = 0

const fetchPullRequest = async () => {
    const requestId = ++latestRequest
    pullRequest.value = null
    commits.value = null
    fetchError.value = ""

    if (!props.ticket.pull_request_url || !props.routes.pull_request) {
        isFetching.value = false
        return
    }

    isFetching.value = true
    try {
        const { data } = await axios.get(route(props.routes.pull_request.name, props.routes.pull_request.parameters), { params: props.compact ? {} : { with_commits: 1 } })
        if (requestId !== latestRequest) return
        pullRequest.value = data.pull_request
        commits.value = data.commits ?? null
        fetchError.value = data.error ?? ""
    } catch {
        if (requestId === latestRequest) fetchError.value = ctrans("Could not load the pull request")
    } finally {
        if (requestId === latestRequest) isFetching.value = false
    }
}

watch([() => props.ticket.id, () => props.ticket.pull_request_url], fetchPullRequest, { immediate: true })

const isEditing = ref(false)
const draftUrl = ref("")
const saveError = ref("")
const isSaving = ref(false)
const input = ref<HTMLInputElement | null>(null)

const canEdit = computed(() => Boolean(props.canEdit && props.routes.pull_request_update))

const startEdit = async () => {
    draftUrl.value = props.ticket.pull_request_url ?? ""
    saveError.value = ""
    isEditing.value = true
    await nextTick()
    input.value?.focus()
    input.value?.select()
}

const cancelEdit = () => {
    if (isSaving.value) return
    isEditing.value = false
    saveError.value = ""
}

const save = () => {
    if (isSaving.value || !props.routes.pull_request_update) return

    if (draftUrl.value.trim() === (props.ticket.pull_request_url ?? "")) {
        cancelEdit()
        return
    }

    router.patch(
        route(props.routes.pull_request_update.name, props.routes.pull_request_update.parameters),
        { pull_request_url: draftUrl.value.trim() || null },
        {
            preserveScroll: true,
            onStart: () => {
                isSaving.value = true
                saveError.value = ""
            },
            onError: (errors) => (saveError.value = errors.pull_request_url ?? Object.values(errors)[0] ?? ctrans("Could not save the link")),
            onFinish: () => (isSaving.value = false),
            onSuccess: () => {
                isEditing.value = false
                emit("updated")
            },
        }
    )
}

const formatDate = (value: string | null) => (value ? new Date(value).toLocaleDateString([], { day: "numeric", month: "short", year: "numeric" }) : "")

const formatDateTime = (value: string) => new Date(value).toLocaleString([], { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hourCycle: "h23" })

const openedOn =computed(() => formatDate(pullRequest.value?.created_at ?? null))

const readStoredFlag = (key: string, offValue: string) => {
    try {
        return localStorage.getItem(key) !== offValue
    } catch {
        return true
    }
}

const storeFlag = (key: string, value: string) => {
    try {
        localStorage.setItem(key, value)
    } catch {}
}

const isStoredOpen = (key: string) => {
    try {
        return localStorage.getItem(key) === "open"
    } catch {
        return false
    }
}

const isCommitsOpen = ref(isStoredOpen("ticket_pull_request_commits_open"))
const isDeployedCommitsOpen = ref(isStoredOpen("ticket_deployed_commits_open"))

const toggleDeployedCommits = () => {
    isDeployedCommitsOpen.value = !isDeployedCommitsOpen.value
    storeFlag("ticket_deployed_commits_open", isDeployedCommitsOpen.value ? "open" : "closed")
}

const toggleCommits = () => {
    isCommitsOpen.value = !isCommitsOpen.value
    storeFlag("ticket_pull_request_commits_open", isCommitsOpen.value ? "open" : "closed")
}

const isCommitsNewestFirst = ref(readStoredFlag("ticket_pull_request_commits_order", "oldest"))

const toggleCommitsOrder = () => {
    isCommitsNewestFirst.value = !isCommitsNewestFirst.value
    storeFlag("ticket_pull_request_commits_order", isCommitsNewestFirst.value ? "newest" : "oldest")
}

const sortedCommits = computed(() => (isCommitsNewestFirst.value ? [...(commits.value ?? [])].reverse() : commits.value ?? []))

const expandedCommits = ref<string[]>([])

const toggleDeployedCommit = (hash: string) => {
    expandedCommits.value = expandedCommits.value.includes(hash)
        ? expandedCommits.value.filter((expanded) => expanded !== hash)
        : [...expandedCommits.value, hash]
}
</script>

<template>
    <div :class="compact ? 'text-sm' : 'overflow-hidden rounded-lg border border-gray-300 bg-white p-4 text-sm'">
        <div class="flex items-center justify-between gap-2" :class="compact ? 'mb-1' : 'mb-2'">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Pull request") }}</p>
            <button v-if="!compact && canEdit && !isEditing && ticket.pull_request_url" v-tooltip="ctrans('Change link')" type="button" class="rounded p-1 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-700" @click="startEdit">
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-if="isEditing">
            <div class="flex items-center gap-1.5">
                <input
                    ref="input"
                    v-model="draftUrl"
                    type="url"
                    class="min-w-0 flex-1 rounded border-gray-300 text-sm"
                    :class="saveError && '!border-red-400'"
                    :placeholder="ctrans('https://github.com/owner/repo/pull/123')"
                    :disabled="isSaving"
                    :aria-invalid="Boolean(saveError)"
                    @keydown.enter.prevent="save"
                    @keydown.esc.prevent="cancelEdit" />
                <button v-tooltip="ctrans('Save')" type="button" class="rounded p-1.5 text-green-600 transition duration-200 hover:bg-gray-100 disabled:opacity-60" :disabled="isSaving" @click="save">
                    <FontAwesomeIcon :icon="isSaving ? 'fal fa-spinner' : 'fal fa-save'" :spin="isSaving" fixed-width />
                </button>
                <button v-tooltip="ctrans('Cancel')" type="button" class="rounded p-1.5 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-400" :disabled="isSaving" @click="cancelEdit">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
            </div>
            <p v-if="isSaving" class="mt-1 text-xs text-gray-400">{{ ctrans("Checking the pull request on GitHub") }}</p>
            <p v-else-if="saveError" class="mt-1 text-xs text-red-600" role="alert">{{ saveError }}</p>
            <p v-else-if="!compact" class="mt-1 text-xs text-gray-400">{{ ctrans("Leave empty to unlink the pull request") }}</p>
        </div>

        <div v-else-if="!ticket.pull_request_url" class="flex items-center gap-1.5">
            <span class="italic text-gray-400">{{ ctrans("No pull request linked") }}</span>
            <button v-if="canEdit" v-tooltip="ctrans('Link a pull request')" type="button" class="rounded p-1 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-[--app-accent-strong]" @click="startEdit">
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-else-if="isFetching" aria-busy="true" :class="!compact && 'space-y-2'">
            <template v-if="compact">
                <div class="flex items-center gap-2">
                    <div class="h-3.5 w-2/3 animate-pulse rounded bg-gray-200" />
                    <div class="h-3.5 w-14 animate-pulse rounded bg-gray-200" />
                </div>
            </template>
            <template v-else>
                <div class="h-4 w-3/4 animate-pulse rounded bg-gray-200" />
                <div class="h-3 w-1/3 animate-pulse rounded bg-gray-100" />
                <div class="flex items-center gap-2">
                    <div class="h-6 w-6 animate-pulse rounded-full bg-gray-200" />
                    <div class="h-3 w-24 animate-pulse rounded bg-gray-100" />
                </div>
                <div class="h-12 w-full animate-pulse rounded bg-gray-100" />
            </template>
            <p class="mt-1 text-xs text-gray-400">
                <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width class="mr-1" aria-hidden="true" />{{ ctrans("We're currently fetching PR data") }}
            </p>
        </div>

        <div v-else-if="!pullRequest">
            <div class="flex min-w-0 items-center gap-1.5">
                <a :href="ticket.pull_request_url" target="_blank" rel="noopener noreferrer" class="min-w-0 truncate text-[--app-accent-strong] hover:underline">{{ ticket.pull_request_url }}</a>
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width class="shrink-0 text-gray-400" aria-hidden="true" />
                <button v-if="compact && canEdit" v-tooltip="ctrans('Change link')" type="button" class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" @click="startEdit">
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                </button>
            </div>
            <p v-if="fetchError" class="mt-1 text-xs text-amber-700">{{ fetchError }}</p>
        </div>

        <div v-else-if="compact" class="flex min-w-0 items-center gap-1.5">
            <a :href="pullRequest.url" target="_blank" rel="noopener noreferrer" class="flex min-w-0 items-center gap-1 text-[--app-accent-strong] hover:underline" :title="pullRequest.title">
                <span class="truncate">{{ pullRequest.title }}</span>
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width class="shrink-0" aria-hidden="true" />
            </a>
            <span class="text-gray-400">-</span>
            <span class="shrink-0 rounded-md px-2 py-0.5 text-xs font-medium" :class="stateClasses[pullRequest.state]">{{ pullRequest.state_label }}</span>
            <button v-if="canEdit" v-tooltip="ctrans('Change link')" type="button" class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" @click="startEdit">
                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-else class="space-y-2">
            <a :href="pullRequest.url" target="_blank" rel="noopener noreferrer" class="group flex items-start gap-1.5 font-medium text-gray-800 hover:text-[--app-accent-strong]">
                <span class="group-hover:underline">{{ pullRequest.title }}</span>
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width class="mt-0.5 shrink-0 text-gray-400 group-hover:text-[--app-accent-strong]" aria-hidden="true" />
            </a>
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <span class="rounded px-1.5 py-0.5 font-medium" :class="stateClasses[pullRequest.state]">{{ pullRequest.state_label }}</span>
                <span><FontAwesomeIcon icon="fal fa-code-branch" fixed-width aria-hidden="true" /> {{ pullRequest.repository }} #{{ pullRequest.number }}</span>
            </div>
            <div v-if="pullRequest.author.login" class="flex items-center gap-2 text-xs text-gray-500">
                <img v-if="pullRequest.author.avatar" :src="pullRequest.author.avatar" :alt="pullRequest.author.login" class="h-6 w-6 rounded-full" loading="lazy" />
                <span>
                    {{ ctrans("Opened by") }}
                    <a v-if="pullRequest.author.url" :href="pullRequest.author.url" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">{{ pullRequest.author.login }}</a>
                    <span v-else class="font-medium text-gray-700">{{ pullRequest.author.login }}</span>
                    <template v-if="openedOn"> · {{ openedOn }}</template>
                </span>
            </div>
            <div v-if="pullRequest.body?.trim()" class="max-h-60 overflow-y-auto rounded-md bg-gray-50 px-3 py-2 text-xs">
                <TicketBody :text="pullRequest.body" />
            </div>
            <p v-else class="text-xs italic text-gray-400">{{ ctrans("No description") }}</p>

            <div v-if="commits" class="border-t border-gray-100 pt-2">
                <button type="button" class="flex w-full items-center justify-between gap-2 rounded py-1 text-left text-xs text-gray-500 transition duration-200 hover:text-gray-800" :aria-expanded="isCommitsOpen" @click="toggleCommits">
                    <span class="flex items-center gap-1.5 font-medium">
                        <FontAwesomeIcon icon="fal fa-code-commit" fixed-width aria-hidden="true" />
                        {{ ctrans("Commits") }}
                        <span class="rounded bg-gray-100 px-1.5 text-[11px] tabular-nums text-gray-600">{{ commits.length }}</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3">
                        <span v-if="isCommitsOpen && commits.length > 1" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort commits')" @click.stop="toggleCommitsOrder">
                            {{ isCommitsNewestFirst ? "↓" : "↑" }} {{ isCommitsNewestFirst ? ctrans("Newest first") : ctrans("Oldest first") }}
                        </span>
                        <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isCommitsOpen && '-rotate-90'" aria-hidden="true" />
                    </span>
                </button>
                <ol v-show="isCommitsOpen" class="mt-1 max-h-72 space-y-2 overflow-y-auto pr-1">
                    <li v-for="commit in sortedCommits" :key="commit.sha" class="flex items-start gap-2 text-xs">
                        <img v-if="commit.author_avatar" :src="commit.author_avatar" :alt="commit.author ?? ''" class="mt-0.5 h-5 w-5 shrink-0 rounded-full" loading="lazy" />
                        <span v-else class="mt-0.5 h-5 w-5 shrink-0 rounded-full bg-gray-200" aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-gray-800" :title="commit.message">{{ commit.subject }}</p>
                            <p class="text-gray-400">
                                <a :href="commit.url" target="_blank" rel="noopener noreferrer" class="font-mono text-[--app-accent-strong] hover:underline">{{ commit.short_sha }}</a>
                                <template v-if="commit.author"> · {{ commit.author }}</template>
                                <template v-if="commit.date"> · {{ formatDate(commit.date) }}</template>
                            </p>
                        </div>
                    </li>
                    <li v-if="!commits.length" class="text-xs italic text-gray-400">{{ ctrans("No commits") }}</li>
                </ol>
            </div>
        </div>

        <div v-if="!compact && ticket.commits?.length" class="mt-3 border-t border-gray-100 pt-3">
            <button type="button" class="flex w-full items-center justify-between gap-2 rounded py-1 text-left text-xs text-gray-500 transition duration-200 hover:text-gray-800" :aria-expanded="isDeployedCommitsOpen" @click="toggleDeployedCommits">
                <span class="flex items-center gap-1.5 font-medium">
                    <FontAwesomeIcon icon="fal fa-code-commit" fixed-width aria-hidden="true" />
                    {{ ctrans("Deployed commits") }}
                    <span class="rounded bg-gray-100 px-1.5 text-[11px] tabular-nums text-gray-600">{{ ticket.commits.length }}</span>
                </span>
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isDeployedCommitsOpen && '-rotate-90'" aria-hidden="true" />
            </button>
            <ol v-show="isDeployedCommitsOpen" class="mt-1 space-y-2">
                <li v-for="commit in ticket.commits" :key="commit.hash" class="text-xs">
                    <p
                        class="cursor-pointer break-words text-gray-800"
                        :class="!expandedCommits.includes(commit.hash) && 'line-clamp-2'"
                        :title="commit.subject"
                        @click="toggleDeployedCommit(commit.hash)">{{ commit.subject }}</p>
                    <p class="text-gray-400">
                        <a v-if="commit.url" :href="commit.url" target="_blank" rel="noopener noreferrer" class="font-mono text-[--app-accent-strong] hover:underline">{{ commit.hash.slice(0, 8) }}</a>
                        <span v-else class="font-mono">{{ commit.hash.slice(0, 8) }}</span>
                        <template v-if="commit.version || commit.deployed_at"> · {{ commit.version || ctrans("deployed") }}</template>
                        <template v-if="commit.deployed_at">{{ " · " + formatDateTime(commit.deployed_at) }}</template>
                    </p>
                </li>
            </ol>
        </div>
    </div>
</template>
