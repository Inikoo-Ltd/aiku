<!--
  - Author: Vika Aqordi <aqordivika@yahoo.co.id>
  - Github: aqordeon
  - Copyright (c) 2026, Vika Aqordi
  -->

<script setup lang="ts">
import { Head, InfiniteScroll } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DeploymentChangeLog from "@/Components/DevOps/DeploymentChangeLog.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRocket, faCodeCommit } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faRocket, faCodeCommit)

interface Committer {
    name: string
    email: string
    github_username: string | null
    avatar: string | null
}

interface Deployment {
    id: number
    semantic_version: string | null
    commit_hash: string | null
    short_hash: string | null
    notes: string | null
    change_log: string | null
    committers: Committer[]
    created_at: string | null
}

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    deployments: {
        data: Deployment[]
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-6 max-w-4xl mx-auto">
        <InfiniteScroll data="deployments" manual>
            <div class="flex flex-col gap-y-3">
                <div
                    v-for="deployment in deployments.data"
                    :key="deployment.id"
                    class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-x-4">
                        <div class="flex items-center gap-x-2 min-w-0">
                            <FontAwesomeIcon :icon="faRocket" class="text-indigo-500" fixed-width aria-hidden="true" />
                            <span v-if="deployment.semantic_version" class="font-semibold text-gray-900 tabular-nums">
                                {{ deployment.semantic_version }}
                            </span>
                            <span v-else class="font-semibold text-gray-400 italic">
                                {{ $t('No version') }}
                            </span>
                        </div>
                        <span class="shrink-0 text-xs text-gray-500 tabular-nums">
                            {{ useFormatTime(deployment.created_at, { formatTime: 'short-datetime' }) }}
                        </span>
                    </div>

                    <div v-if="deployment.short_hash" class="mt-2 flex items-center gap-x-1.5 text-xs text-gray-500">
                        <FontAwesomeIcon :icon="faCodeCommit" fixed-width aria-hidden="true" />
                        <span class="font-mono">{{ deployment.short_hash }}</span>
                    </div>

                    <DeploymentChangeLog v-if="deployment.change_log" :text="deployment.change_log" />

                    <div v-if="deployment.committers.length" class="mt-3 flex items-center gap-x-1">
                        <img
                            v-for="committer in deployment.committers"
                            :key="committer.email"
                            :src="committer.avatar ?? undefined"
                            :alt="committer.name"
                            :title="committer.name"
                            class="h-6 w-6 rounded-full border border-white ring-1 ring-gray-200 -ml-1 first:ml-0 object-cover bg-gray-100"
                            loading="lazy"
                            v-tooltip="committer.name"
                        />
                    </div>
                </div>
            </div>

            <template #next="{ loading, fetch, hasMore }">
                <div v-if="hasMore" class="mt-5 flex justify-center">
                    <button
                        type="button"
                        :disabled="loading"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-50"
                        @click="fetch"
                    >
                        {{ loading ? $t('Loading...') : $t('Load more') }}
                    </button>
                </div>
            </template>
        </InfiniteScroll>
    </div>
</template>
