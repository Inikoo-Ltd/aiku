<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import Tag from "primevue/tag"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { faExclamationTriangle, faInfoCircle } from "@fal"
import { routeType } from "@/types/route"

interface WhatsappOutboxRow {
    id: number
    slug: string
    name: string
    code: string
    state: string
    state_icon: { tooltip: string; icon: string; class: string }
    template: { id: number; name: string; language: string | null; category: string | null; is_usable: boolean } | null
    is_sending: boolean
    sent_1w: number
    failed_1w: number
    route: routeType
}

defineProps<{
    data?: {
        is_connected: boolean
        outboxes: WhatsappOutboxRow[]
    }
    tab?: string
}>()
</script>

<template>
    <div class="px-4 py-4 sm:px-6">
        <div v-if="!data" class="space-y-2">
            <div v-for="row in 6" :key="row" class="skeleton h-10 rounded" />
        </div>

        <template v-else>
            <div class="mb-4 flex items-start gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 text-gray-400" fixed-width aria-hidden="true" />
                <span>
                    {{ ctrans("WhatsApp outboxes are switched on and off on their own, apart from the email ones. An active outbox sends its template to customers who opted in to WhatsApp notifications. Only approved UTILITY templates can be used.") }}
                </span>
            </div>

            <div v-if="!data.is_connected" class="mb-4 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                <span>{{ ctrans("This shop has no WhatsApp number connected, so nothing will be sent until one is connected.") }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
                        <tr>
                            <th class="w-8 px-3 py-2"></th>
                            <th class="px-3 py-2">{{ ctrans("Name") }}</th>
                            <th class="px-3 py-2">{{ ctrans("Template") }}</th>
                            <th class="px-3 py-2">{{ ctrans("Status") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Sent 1w") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Failed 1w") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="outbox in data.outboxes" :key="outbox.id">
                            <td class="px-3 py-2">
                                <Icon :data="outbox.state_icon" />
                            </td>
                            <td class="px-3 py-2">
                                <Link :href="route(outbox.route.name, outbox.route.parameters)" class="primaryLink inline-flex items-center gap-2">
                                    <FontAwesomeIcon :icon="faWhatsapp" :class="outbox.is_sending ? 'text-[#25D366]' : 'text-gray-300'" fixed-width aria-hidden="true" />
                                    {{ outbox.name }}
                                </Link>
                            </td>
                            <td class="px-3 py-2">
                                <span v-if="outbox.template" class="whitespace-nowrap">
                                    {{ outbox.template.name }}
                                    <span class="text-xs text-gray-400">{{ [outbox.template.language, outbox.template.category].filter(Boolean).join(" · ") }}</span>
                                </span>
                                <span v-else class="text-xs text-gray-400">{{ ctrans("No template") }}</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <Tag v-if="outbox.is_sending" severity="success" :value="ctrans('Sending')" />
                                <Tag v-else-if="outbox.state === 'active'" severity="warn" :value="ctrans('Not sending')" v-tooltip="ctrans('The template must be an approved UTILITY template.')" />
                                <Tag v-else severity="secondary" :value="ctrans('Off')" />
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ outbox.sent_1w }}</td>
                            <td class="px-3 py-2 text-right tabular-nums" :class="outbox.failed_1w ? 'text-red-600' : ''">{{ outbox.failed_1w }}</td>
                        </tr>
                        <tr v-if="!data.outboxes.length">
                            <td colspan="6" class="px-3 py-6 text-center text-xs text-gray-400">
                                {{ ctrans("No WhatsApp outboxes yet. Run shop:seed_whatsapp_outboxes to create them.") }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
