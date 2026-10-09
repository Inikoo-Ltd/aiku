<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileSignature, faCheckCircle, faExclamationTriangle } from "@fal"

library.add(faFileSignature, faCheckCircle, faExclamationTriangle)

defineProps<{
    data: {
        id: number
        company: string | null
        signed_by: string | null
        position: string | null
        signed_on: string | null
        file: string | null
        received: string | null
        answers: { question: string, answer: string, is_yes: boolean }[]
    }[]
    tab: string
}>()
</script>

<template>
    <div class="p-4 space-y-4 text-sm text-gray-700 max-w-4xl">
        <div v-if="!data?.length" class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-gray-500 flex items-center gap-3">
            <FontAwesomeIcon icon="fal fa-file-signature" class="text-gray-400" fixed-width />
            {{ ctrans("No signed declarations yet. They come from the Supplier declarations tab of the supplier product upload.") }}
        </div>

        <section v-for="declaration in data" :key="declaration.id" class="rounded-lg border border-gray-200 bg-white">
            <header class="px-3 py-2 border-b border-gray-100 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="font-semibold">{{ declaration.signed_by || ctrans("Unsigned") }}</span>
                <span v-if="declaration.position" class="text-gray-500">{{ declaration.position }}</span>
                <span v-if="declaration.company" class="text-gray-500">· {{ declaration.company }}</span>
                <span class="ml-auto text-xs text-gray-500 tabular-nums">
                    {{ declaration.signed_on ? ctrans("Signed :date", { date: declaration.signed_on }) : ctrans("No date") }}
                    <template v-if="declaration.file"> · {{ declaration.file }}</template>
                </span>
            </header>
            <ul class="divide-y divide-gray-100">
                <li v-for="(answer, index) in declaration.answers" :key="index" class="px-3 py-1.5 grid grid-cols-[1.25rem_minmax(0,1fr)_minmax(8rem,30%)] gap-2 items-start">
                    <FontAwesomeIcon
                        :icon="answer.is_yes ? 'fal fa-check-circle' : 'fal fa-exclamation-triangle'"
                        :class="answer.is_yes ? 'text-green-600' : 'text-amber-500'"
                        fixed-width
                        class="mt-0.5"
                    />
                    <span>{{ answer.question }}</span>
                    <span :class="answer.is_yes ? 'text-gray-700' : 'font-medium text-amber-700'">{{ answer.answer || ctrans("Not answered") }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
