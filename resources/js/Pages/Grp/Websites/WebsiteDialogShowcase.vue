<script setup lang="ts">
import { computed, provide, ref } from "vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import Tag from "@/Components/Tag.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { getWebsiteDialogComponent } from "@/Composables/useWebsiteDialog"
import type { WebsiteDialogData } from "@/types/WebsiteDialog"
import type { routeType } from "@/types/route"
import { faDraftingCompass } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faDraftingCompass)

const props = defineProps<{
    data: WebsiteDialogData & {
        published_layout?: {
            template_code?: string
            component?: string
            fields?: {}
            container_properties?: {}
        } | null
        workshop_route?: routeType | null
    }
    tab?: string
}>()

provide("screenType", ref("desktop"))

const shownLayout = computed(() => props.data.published_layout ?? props.data)
const dialogComponent = computed(() => getWebsiteDialogComponent(shownLayout.value?.component))
</script>

<template>
    <div class="space-y-4 py-3">
        <div class="border-b border-gray-200 pb-4">
            <div v-if="dialogComponent" class="mx-4 flex flex-col items-center gap-y-2 rounded-md bg-gray-800/70 p-8">
                <component :is="dialogComponent" :dialogData="shownLayout" />
                <div class="text-xs italic text-white/80">
                    {{ data.published_layout ? ctrans("Published version") : ctrans("Draft, not published yet") }}
                </div>
            </div>

            <EmptyState
                v-else
                :data="{
                    title: ctrans('This dialog is empty'),
                    description: ctrans('Pick a template in the workshop to get started'),
                    action: data.workshop_route ? {
                        label: ctrans('Workshop'),
                        tooltip: ctrans('Workshop'),
                        style: 'tertiary',
                        icon: ['fal', 'fa-drafting-compass'],
                        route: data.workshop_route
                    } : undefined
                }"
            />
        </div>

        <div class="grid grid-cols-1 gap-x-8 gap-y-6 px-4 md:px-6 lg:grid-cols-2 lg:px-8">
            <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-medium">{{ ctrans("Detail information") }}:</h3>
                </div>

                <dl class="space-y-4 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium">{{ ctrans("Name") }}</dt>
                        <dd class="text-lg font-semibold">{{ data.name }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Shown") }}</dt>
                        <dd class="text-sm">{{ data.display_frequency }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Created at") }}</dt>
                        <dd class="text-sm">{{ useFormatTime(data.created_at, { formatTime: 'hm' }) }}</dd>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Start") }}</dt>
                        <dd class="flex items-center gap-x-2 text-sm">
                            <template v-if="data.live_at">
                                <Tag
                                    :label="data.schedule_at ? ctrans('Scheduled publish') : ctrans('Instant publish')"
                                    :theme="data.schedule_at ? 1 : 3"
                                    noHoverColor
                                />
                                {{ useFormatTime(data.live_at, { formatTime: 'hm' }) }}
                            </template>
                            <span v-else class="italic text-gray-400">{{ ctrans("Not published yet") }}</span>
                        </dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Finish") }}</dt>
                        <dd class="text-sm">
                            <span v-if="data.schedule_finish_at">{{ useFormatTime(data.schedule_finish_at, { formatTime: 'hm' }) }}</span>
                            <span v-else class="italic text-gray-400">{{ ctrans("No date specified") }}</span>
                        </dd>
                    </div>

                    <div v-if="data.paused_by" class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Paused by") }}</dt>
                        <dd class="text-sm">
                            {{ data.paused_by }}
                            <span v-if="data.paused_until">({{ ctrans("back on :date", { date: useFormatTime(data.paused_until, { formatTime: 'hm' }) }) }})</span>
                        </dd>
                    </div>

                    <div v-if="data.publisher?.contact_name" class="flex items-center justify-between border-t border-gray-200 pt-4">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Last Publisher") }}</dt>
                        <dd class="text-sm"><span class="font-bold">{{ data.publisher.contact_name }}</span> ({{ data.publisher.username }})</dd>
                    </div>

                    <div v-if="data.ready_at" class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Last Published time") }}</dt>
                        <dd class="text-sm">{{ useFormatTime(data.ready_at, { formatTime: 'hm' }) }}</dd>
                    </div>

                    <div v-if="data.published_message" class="flex flex-col items-start">
                        <dt class="text-sm font-medium text-gray-600">{{ ctrans("Last Published message") }}</dt>
                        <dd class="mt-1 w-full rounded border border-gray-300 bg-gray-700/5 px-3 py-2 text-sm italic text-gray-500">
                            {{ data.published_message }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</template>
