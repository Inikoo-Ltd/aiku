<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ctrans } from '@/Composables/useTrans'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHandshake, faInfoCircle, faExclamationTriangle, faThumbtack, faPrint } from '@fal'
import { faExclamationCircle } from '@fas'
import { noteCategoryClass, noteSeverityIcon } from '@/Components/Procurement/procurementNoteColours'

library.add(faHandshake, faInfoCircle, faExclamationTriangle, faThumbtack, faPrint, faExclamationCircle)

defineProps<{
    agreement: {
        terms: {
            id: number
            term: string
            value: string
            level: string
            level_label: string
            product_code: string | null
            organisation_code: string | null
            agreed_on: string
            agreed_by: string | null
            show_on_po: boolean
        }[]
        notes: {
            id: number
            title: string | null
            note: string | null
            category: { value: string, label: string, colour: string }
            severity: string
            is_pinned: boolean
            author: string | null
        }[]
        open_incidents: number
        print_route?: { name: string, parameters: object } | null
        suppliers?: { code: string, name: string, warnings: number, open_incidents: number }[] | null
    }
    showLevel?: boolean
}>()
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white text-sm text-gray-700">
        <header class="flex items-center gap-2 border-b border-gray-100 px-3 py-2">
            <FontAwesomeIcon icon="fal fa-handshake" class="text-gray-400" fixed-width aria-hidden="true" />
            <span class="font-semibold text-gray-900">{{ ctrans('What was agreed') }}</span>
            <span class="ml-auto"></span>
            <span v-if="agreement.open_incidents" class="rounded-full bg-gray-800 px-2 py-0.5 text-xs font-medium text-white tabular-nums">
                {{ agreement.open_incidents === 1 ? ctrans('1 open incident') : ctrans(':count open incidents', { count: agreement.open_incidents }) }}
            </span>
            <a v-if="agreement.print_route" :href="route(agreement.print_route.name, agreement.print_route.parameters)" target="_blank" class="flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-50">
                <FontAwesomeIcon icon="fal fa-print" class="text-gray-500" fixed-width aria-hidden="true" />
                {{ ctrans('Handover sheet') }}
            </a>
        </header>

        <div v-if="!agreement.terms.length && !agreement.notes.length" class="px-3 py-4 text-gray-500">
            {{ ctrans('Nothing recorded yet: no agreed terms and no pinned notes.') }}
        </div>

        <table v-if="agreement.terms.length" class="w-full">
            <tbody class="divide-y divide-gray-100">
                <tr v-for="term in agreement.terms" :key="term.id">
                    <td class="w-1/3 px-3 py-1.5 align-top font-medium text-gray-900">
                        {{ term.term }}
                        <span v-if="term.product_code" class="font-normal text-gray-500">({{ term.product_code }})</span>
                    </td>
                    <td class="px-3 py-1.5 align-top">{{ term.value }}</td>
                    <td class="px-3 py-1.5 text-right align-top text-xs text-gray-500 whitespace-nowrap">
                        <span v-if="showLevel" class="mr-2 rounded border border-gray-200 px-1.5 py-0.5">{{ term.level_label }}</span>
                        <span v-if="term.organisation_code" class="mr-2 rounded border border-gray-200 px-1.5 py-0.5">{{ ctrans('Only :organisation', { organisation: term.organisation_code }) }}</span>
                        <span class="tabular-nums">{{ term.agreed_on }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <ul v-if="agreement.notes.length" class="flex flex-col gap-2 border-t border-gray-100 p-3">
            <li v-for="note in agreement.notes" :key="note.id" class="rounded border-l-4 px-3 py-1.5" :class="noteCategoryClass[note.category.colour]">
                <div class="flex items-center gap-2">
                    <FontAwesomeIcon :icon="noteSeverityIcon[note.severity].icon" :class="note.category.colour === 'black' ? 'text-white' : noteSeverityIcon[note.severity].class" fixed-width aria-hidden="true" />
                    <span class="font-medium">{{ note.title ?? note.note }}</span>
                    <span class="ml-auto text-xs opacity-75">{{ note.category.label }}</span>
                </div>
                <div v-if="note.title && note.note" class="mt-0.5 whitespace-pre-line text-xs opacity-90">{{ note.note }}</div>
            </li>
        </ul>

        <div v-if="agreement.suppliers" class="border-t border-gray-100">
            <div class="px-3 pt-2 text-xs font-medium uppercase text-gray-500">{{ ctrans('Suppliers to watch') }}</div>
            <div v-if="!agreement.suppliers.length" class="px-3 py-2 text-gray-500">
                {{ ctrans('None of this agent\'s suppliers has a warning or an open incident.') }}
            </div>
            <table class="w-full">
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="supplier in agreement.suppliers" :key="supplier.code">
                        <td class="px-3 py-1.5 font-medium text-gray-900">{{ supplier.code }}</td>
                        <td class="px-3 py-1.5">{{ supplier.name }}</td>
                        <td class="px-3 py-1.5 text-right text-xs whitespace-nowrap tabular-nums">
                            <span v-if="supplier.warnings" class="text-amber-700">
                                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                                {{ supplier.warnings === 1 ? ctrans('1 warning') : ctrans(':count warnings', { count: supplier.warnings }) }}
                            </span>
                            <span v-if="supplier.open_incidents" class="ml-2 rounded-full bg-gray-800 px-2 py-0.5 font-medium text-white">
                                {{ supplier.open_incidents === 1 ? ctrans('1 open incident') : ctrans(':count open incidents', { count: supplier.open_incidents }) }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
