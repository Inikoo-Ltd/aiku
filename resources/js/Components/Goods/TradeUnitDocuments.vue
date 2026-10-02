<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFilePdf, faInfoCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

interface TradeUnitDocument {
    media_id: number
    scope: string
    scope_label: string
    name: string
    download_route: routeType
    source: { type: string, label: string, route: routeType } | null
}

const props = withDefaults(defineProps<{
    data?: { documents?: TradeUnitDocument[] }
    documents?: TradeUnitDocument[]
    title?: string
    note?: string
}>(), {
    note: "Documents are added on the trade unit or its trade unit family. Change them there.",
})

const rows = computed(() => props.documents ?? props.data?.documents ?? [])
</script>

<template>
    <div class="px-10 py-4">
        <h3 v-if="title" class="mb-2 text-base font-semibold text-gray-700">{{ title }}</h3>
        <p class="mb-3 flex items-center gap-2 text-sm text-gray-500">
            <FontAwesomeIcon :icon="faInfoCircle" fixed-width />
            {{ ctrans(note) }}
        </p>

        <div v-if="!rows.length" class="text-sm text-gray-400">
            {{ ctrans("No documents") }}
        </div>

        <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2 pr-4 font-medium">{{ ctrans("Type") }}</th>
                    <th class="py-2 pr-4 font-medium">{{ ctrans("File") }}</th>
                    <th class="py-2 font-medium">{{ ctrans("From") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="document in rows" :key="document.media_id + '-' + document.scope + '-' + (document.source?.label ?? '')">
                    <td class="py-2 pr-4 text-gray-700">{{ document.scope_label }}</td>
                    <td class="py-2 pr-4">
                        <a :href="route(document.download_route.name, document.download_route.parameters)" target="_blank" class="primaryLink">
                            <FontAwesomeIcon :icon="faFilePdf" class="mr-1" fixed-width />{{ document.name }}
                        </a>
                    </td>
                    <td class="py-2">
                        <Link v-if="document.source" :href="route(document.source.route.name, document.source.route.parameters)" class="primaryLink">
                            {{ document.source.label }}
                        </Link>
                        <span v-else class="text-red-600">{{ ctrans("Not from its trade unit") }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
