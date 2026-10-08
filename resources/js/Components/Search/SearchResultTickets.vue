<!--
  - Author: Rifqi Taufiqurrohman <rifqitaufiqurrohman1@gmail.com>
  - Created: Wed, 07 Oct 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Skeleton from 'primevue/skeleton'
import { computed, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faRocket, faSpinnerThird, faCircle, faUserCheck, faSpinner,
    faClock, faCommentDots, faCheckCircle, faBan,
} from '@fal'
import Icon from '@/Components/Icon.vue'
import { ctrans } from '@/Composables/useTrans'
import { Icon as IconTS } from '@/types/Utils/Icon'

library.add(
    faRocket, faSpinnerThird, faCircle, faUserCheck, faSpinner,
    faClock, faCommentDots, faCheckCircle, faBan,
)

type DeployedCommit = {
    hash: string
    subject: string
    version: string | null
    deployed_at: string | null
}

type TicketResult = {
    id: number
    code: string
    name: string
    state_icon?: IconTS
    commits: DeployedCommit[]
    href: string
}

const model = defineModel('open')

const props = defineProps<{
    results: { tickets?: TicketResult[] } | null
    isLoading: boolean
    query: string
}>()

const tickets = computed(() => props.results?.tickets ?? [])
const loadingId = ref<number | null>(null)

const deployedOn = (commit: DeployedCommit) =>
    commit.deployed_at ? new Date(commit.deployed_at).toLocaleDateString() : ''
</script>

<template>
    <div class="col-span-12 flex flex-col min-h-0">
        <div class="flex-1 p-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-3">
                <div v-for="i in 6" :key="i" class="p-4 rounded-md border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="30%" height="1rem" />
                        <Skeleton width="60px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="70%" height="0.75rem" />
                </div>
            </div>

            <div v-else-if="tickets.length">
                <Link
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    :href="ticket.href"
                    class="block p-4 mb-3 rounded-md border border-transparent bg-slate-50 cursor-pointer hover:border-slate-200 hover:bg-slate-100 hover:shadow-sm"
                    @start="() => { model = false; loadingId = ticket.id }"
                    @finish="() => loadingId = null"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="flex items-center gap-2 min-w-0 text-sm font-semibold">
                            <span class="font-mono">{{ ticket.code }}</span>
                            <span class="truncate font-normal text-slate-600">{{ ticket.name }}</span>
                        </p>
                        <span v-if="loadingId === ticket.id" class="shrink-0 text-slate-400">
                            <FontAwesomeIcon icon="fal fa-spinner-third" spin fixed-width aria-hidden="true" />
                        </span>
                        <Icon v-else-if="ticket.state_icon" :data="ticket.state_icon" size="2xs" class="shrink-0" />
                    </div>

                    <ul v-if="ticket.commits.length" class="mt-2 space-y-1 border-t border-slate-200 pt-2 text-xs">
                        <li v-for="commit in ticket.commits" :key="commit.hash" class="flex items-start gap-2 min-w-0">
                            <span class="shrink-0 font-mono text-slate-700">{{ commit.hash.slice(0, 10) }}</span>
                            <span class="truncate text-gray-500">{{ commit.subject }}</span>
                            <span v-if="commit.version || commit.deployed_at" class="shrink-0 inline-flex items-center gap-1 text-gray-400">
                                <FontAwesomeIcon icon="fal fa-rocket" fixed-width aria-hidden="true" />
                                {{ commit.version || ctrans('deployed') }} {{ deployedOn(commit) }}
                            </span>
                        </li>
                    </ul>
                </Link>
            </div>

            <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                {{ ctrans('No results') }}
            </div>
        </div>
    </div>
</template>
