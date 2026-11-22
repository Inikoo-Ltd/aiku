<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFileSignature, faFilePdf, faChevronRight } from '@fal'
import SupplierAgreementStatusBadge from '@/Components/Procurement/SupplierAgreementStatusBadge.vue'

library.add(faFileSignature, faFilePdf, faChevronRight)

type Status = { status: string, label: string, colour: string, icon: string, accepted_version: number | null, current_version: number | null, newer_version_to_read: boolean }

defineProps<{
    supplierAgreement: (Status & {
        history?: {
            id: number
            version: number
            supplier_legal_name: string
            authorised_person: string
            position: string
            accepted_at: string
            country_code: string | null
            city: string | null
            pdf_route: { name: string, parameters: Record<string, string | number> }
        }[]
    }) | {
        suppliers: (Status & { code: string, name: string, slug: string | null, accepted_at: string | null, authorised_person?: string | null, position?: string | null })[]
        not_accepted: number
        suppliers_route: { name: string, parameters: (string | number)[] } | null
    }
}>()
</script>

<template>
    <div class="border-t border-gray-100 text-sm text-gray-700">
        <div class="flex items-center gap-2 px-3 py-2">
            <FontAwesomeIcon icon="fal fa-file-signature" class="text-gray-400" fixed-width aria-hidden="true" />
            <span class="text-xs font-medium uppercase text-gray-500">{{ ctrans('Supplier Agreement') }}</span>
            <SupplierAgreementStatusBadge v-if="'status' in supplierAgreement" :status="supplierAgreement" class="ml-auto" />
            <component :is="supplierAgreement.suppliers_route ? Link : 'span'" v-else-if="supplierAgreement.suppliers.length" :href="supplierAgreement.suppliers_route ? route(supplierAgreement.suppliers_route.name, supplierAgreement.suppliers_route.parameters) : undefined" class="ml-auto inline-flex items-center gap-1 text-xs" :class="[supplierAgreement.not_accepted ? 'text-red-700' : 'text-green-700', supplierAgreement.suppliers_route && 'hover:underline']">
                {{ ctrans(':accepted/:total accepted (:percent% complete)', { accepted: supplierAgreement.suppliers.length - supplierAgreement.not_accepted, total: supplierAgreement.suppliers.length, percent: Math.floor(100 * (supplierAgreement.suppliers.length - supplierAgreement.not_accepted) / supplierAgreement.suppliers.length) }) }}
                <FontAwesomeIcon v-if="supplierAgreement.suppliers_route" icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
            </component>
        </div>

        <template v-if="'status' in supplierAgreement">
            <p v-if="supplierAgreement.newer_version_to_read" class="px-3 pt-1 text-xs text-gray-500">
                {{ ctrans('Version :version was published since, without asking to accept again.', { version: supplierAgreement.current_version }) }}
            </p>
            <p v-if="!supplierAgreement.history?.length" class="px-3 py-2 text-gray-500">{{ ctrans('Never accepted.') }}</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full">
                    <thead class="whitespace-nowrap text-left text-xs text-gray-500">
                        <tr>
                            <th class="px-3 py-1 font-medium">{{ ctrans('Version') }}</th>
                            <th class="px-3 py-1 font-medium">{{ ctrans('Accepted by') }}</th>
                            <th class="px-3 py-1 font-medium">{{ ctrans('Date') }}</th>
                            <th class="px-3 py-1"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="acceptance in supplierAgreement.history" :key="acceptance.id">
                            <td class="whitespace-nowrap px-3 py-1.5 font-medium text-gray-900">v{{ acceptance.version }}</td>
                            <td class="px-3 py-1.5">
                                {{ acceptance.authorised_person }}<span class="text-gray-500">, {{ acceptance.position }}</span>
                                <div class="text-xs text-gray-500">{{ acceptance.supplier_legal_name }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-1.5 tabular-nums">
                                {{ useFormatTime(acceptance.accepted_at, { formatTime: 'short-datetime' }) }}
                                <div v-if="acceptance.city || acceptance.country_code" class="text-xs text-gray-500">{{ [acceptance.city, acceptance.country_code].filter(Boolean).join(', ') }}</div>
                            </td>
                            <td class="px-3 py-1.5 text-right">
                                <a :href="route(acceptance.pdf_route.name, acceptance.pdf_route.parameters)" target="_blank" class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50">
                                    <FontAwesomeIcon icon="fal fa-file-pdf" class="text-gray-500" fixed-width aria-hidden="true" />
                                    PDF
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

    </div>
</template>
