<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMicrophone } from "@fal"

library.add(faMicrophone)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    recording: {
        ulid: string
        status: "recording" | "stopped" | "processing" | "done" | "failed"
        title: string | null
        started_by: string
        started_at: string
        duration_seconds: number | null
        people: string[]
        summary: { summary?: string; decisions?: string[]; action_items?: string[] } | null
        transcript: { at: number; speaker: string; text: string }[]
        audio: "deleted" | "kept" | "pending"
        delete_audio_at: string | null
        missing_speakers: string[]
    }
}>()

const clock = (seconds: number) => `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-6 text-sm text-gray-700">
        <p class="text-gray-500">
            {{ recording.title ? recording.title + " · " : "" }}{{ useFormatTime(recording.started_at, { formatTime: "short-datetime" }) }}
            <template v-if="recording.duration_seconds"> · {{ ctrans(":minutes min", { minutes: Math.max(1, Math.round(recording.duration_seconds / 60)) }) }}</template>
            · {{ recording.people.join(", ") }}
        </p>

        <p v-if="recording.status === 'recording' || recording.status === 'stopped' || recording.status === 'processing'" class="text-gray-500">
            {{ ctrans("The summary is being written. This page shows it once it is ready.") }}
        </p>
        <p v-else-if="recording.status === 'failed'" class="text-red-600">
            {{ ctrans("The summary could not be written for this call.") }}
        </p>

        <template v-if="recording.summary">
            <section>
                <h2 class="mb-1 font-semibold text-gray-900">{{ ctrans("Summary") }}</h2>
                <p class="whitespace-pre-line">{{ recording.summary.summary }}</p>
            </section>
            <section v-if="recording.summary.decisions?.length">
                <h2 class="mb-1 font-semibold text-gray-900">{{ ctrans("Decisions") }}</h2>
                <ul class="list-disc space-y-0.5 pl-5">
                    <li v-for="(decision, index) in recording.summary.decisions" :key="index">{{ decision }}</li>
                </ul>
            </section>
            <section v-if="recording.summary.action_items?.length">
                <h2 class="mb-1 font-semibold text-gray-900">{{ ctrans("Action items") }}</h2>
                <ul class="list-disc space-y-0.5 pl-5">
                    <li v-for="(item, index) in recording.summary.action_items" :key="index">{{ item }}</li>
                </ul>
            </section>
        </template>

        <p v-if="recording.missing_speakers.length" class="text-amber-700">
            {{ ctrans("Could not record :names, so what they said is not in the transcript or the summary.", { names: recording.missing_speakers.join(", ") }) }}
        </p>

        <section v-if="recording.transcript.length">
            <h2 class="mb-2 font-semibold text-gray-900">{{ ctrans("Transcript") }}</h2>
            <div v-for="(line, index) in recording.transcript" :key="index" class="flex gap-3 py-0.5">
                <span class="w-12 shrink-0 tabular-nums text-gray-400">{{ clock(line.at) }}</span>
                <span><span class="font-medium text-gray-900">{{ line.speaker }}:</span> {{ line.text }}</span>
            </div>
        </section>

        <p class="text-xs text-gray-400">
            <template v-if="recording.audio === 'kept' && recording.delete_audio_at">{{ ctrans("The audio is kept until :date, then deleted.", { date: useFormatTime(recording.delete_audio_at) }) }}</template>
            <template v-else-if="recording.audio === 'deleted'">{{ ctrans("The audio was deleted once the transcript was saved.") }}</template>
            {{ ctrans("Recorded by :name. Only the people in the call can see this page.", { name: recording.started_by }) }}
        </p>
    </div>
</template>
