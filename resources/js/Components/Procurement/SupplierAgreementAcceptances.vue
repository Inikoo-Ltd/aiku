<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'
import { useFormatTime } from '@/Composables/useFormatTime'
import { Link } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFileSignature, faFilePdf } from '@fal'
import SupplierAgreementStatusBadge from '@/Components/Procurement/SupplierAgreementStatusBadge.vue'

library.add(faFileSignature, faFilePdf)

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
    }
}>()
</script>

<template>
    <div class="border-t border-gray-100 text-sm text-gray-700">
        <div class="flex items-center gap-2 px-3 pt-2">
            <FontAwesomeIcon icon="fal fa-file-signature" class="text-gray-400" fixed-width aria-hidden="true" />
            <span class="text-xs font-medium uppercase text-gray-500">{{ ctrans('Supplier Agreement') }}</span>
            <SupplierAgreementStatusBadge v-if="'status' in supplierAgreement" :status="supplierAgreement" class="ml-auto" />
            <span v-else-if="supplierAgreement.not_accepted" class="ml-auto text-xs text-red-700">
                {{ supplierAgreement.not_accepted === 1 ? ctrans('1 supplier still to accept') : ctrans(':count suppliers still to accept', { count: supplierAgreement.not_accepted }) }}
            </span>
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

        <table v-else class="w-full">
            <tbody class="divide-y divide-gray-100">
                <tr v-for="supplier in supplierAgreement.suppliers" :key="supplier.code">
                    <td class="whitespace-nowrap px-3 py-1.5 font-medium text-gray-900">
                        <Link v-if="supplier.slug" :href="route('grp.supply-chain.suppliers.show', [supplier.slug])" class="hover:underline">{{ supplier.code }}</Link>
                        <template v-else>{{ supplier.code }}</template>
                    </td>
                    <td class="px-3 py-1.5">
                        {{ supplier.name }}
                        <div v-if="supplier.accepted_at" class="text-xs text-gray-500">
                            {{ ctrans("v:version on :date", { version: supplier.accepted_version ?? "", date: useFormatTime(supplier.accepted_at) }) }}<template v-if="supplier.authorised_person"> · {{ supplier.authorised_person }}<template v-if="supplier.position">, {{ supplier.position }}</template></template>
                        </div>
                    </td>
                    <td class="px-3 py-1.5 text-right"><SupplierAgreementStatusBadge :status="supplier" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
