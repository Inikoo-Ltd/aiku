<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faPlus, faTrashAlt } from '@fal'

library.add(faPlus, faTrashAlt)

interface ComplianceItem {
    id: number
    type: string
    type_label: string
    reference: string | null
    notes: string | null
    is_required: boolean
    valid_from: string | null
    valid_until: string | null
    expired: boolean
    days_to_expiry: number | null
}

const props = defineProps<{
    data: {
        artefact_id: number
        status: string
        status_label: string
        problems: string[]
        items: ComplianceItem[]
        type_options: { value: string, label: string }[]
        routes: {
            store: { name: string, parameters: object }
            update: { name: string }
            delete: { name: string }
        }
    }
    tab?: string
}>()

const statusStyle: Record<string, string> = {
    not_configured: 'bg-gray-100 text-gray-600 border-gray-200',
    ok: 'bg-green-50 text-green-700 border-green-200',
    expiring: 'bg-amber-50 text-amber-700 border-amber-200',
    problem: 'bg-red-50 text-red-700 border-red-200',
}

const newType = ref<string | null>(null)
const newReference = ref('')
const newValidUntil = ref('')
const newIsRequired = ref(true)
const processing = ref(false)

function store() {
    processing.value = true
    router.post(
        route(props.data.routes.store.name, props.data.routes.store.parameters),
        {
            type: newType.value,
            reference: newReference.value || null,
            valid_until: newValidUntil.value || null,
            is_required: newIsRequired.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false
                newType.value = null
                newReference.value = ''
                newValidUntil.value = ''
                newIsRequired.value = true
            },
        }
    )
}

function update(item: ComplianceItem, changes: object) {
    processing.value = true
    router.patch(
        route(props.data.routes.update.name, { artefactComplianceItem: item.id }),
        changes,
        {
            preserveScroll: true,
            onFinish: () => processing.value = false,
        }
    )
}

function destroy(item: ComplianceItem) {
    processing.value = true
    router.delete(
        route(props.data.routes.delete.name, { artefactComplianceItem: item.id }),
        {
            preserveScroll: true,
            onFinish: () => processing.value = false,
        }
    )
}
</script>

<template>
    <div class="mt-5 px-4 max-w-3xl">
        <div :class="['rounded border px-4 py-3 mb-4 text-sm', statusStyle[data.status]]">
            <div class="font-medium">{{ data.status_label }}</div>
            <ul v-if="data.problems.length" class="mt-1 list-disc list-inside">
                <li v-for="(problem, index) in data.problems" :key="index">{{ problem }}</li>
            </ul>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-gray-200 text-gray-500">
                    <th class="py-2 pr-4">{{ trans('Type') }}</th>
                    <th class="py-2 pr-4">{{ trans('Reference') }}</th>
                    <th class="py-2 pr-4">{{ trans('Required') }}</th>
                    <th class="py-2 pr-4">{{ trans('Valid until') }}</th>
                    <th class="py-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in data.items" :key="item.id" class="border-b border-gray-100">
                    <td class="py-2 pr-4">{{ item.type_label }}</td>
                    <td class="py-2 pr-4">
                        <input
                            type="text"
                            class="w-40 rounded border-gray-300 text-sm"
                            :value="item.reference"
                            @change="update(item, { reference: ($event.target as HTMLInputElement).value || null })"
                        />
                    </td>
                    <td class="py-2 pr-4">
                        <input
                            type="checkbox"
                            :checked="item.is_required"
                            @change="update(item, { is_required: ($event.target as HTMLInputElement).checked })"
                        />
                    </td>
                    <td class="py-2 pr-4">
                        <input
                            type="date"
                            class="rounded border-gray-300 text-sm"
                            :value="item.valid_until"
                            @change="update(item, { valid_until: ($event.target as HTMLInputElement).value || null })"
                        />
                    </td>
                    <td class="py-2 text-right">
                        <button
                            type="button"
                            class="text-gray-400 hover:text-red-600 disabled:opacity-50"
                            :disabled="processing"
                            :title="trans('Remove compliance item')"
                            @click="destroy(item)"
                        >
                            <FontAwesomeIcon :icon="['fal', 'trash-alt']" fixed-width />
                        </button>
                    </td>
                </tr>
                <tr v-if="!data.items.length">
                    <td colspan="5" class="py-6 text-center text-gray-400">
                        {{ trans('No compliance items configured for this artefact.') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 flex items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Type') }}</label>
                <select v-model="newType" class="rounded border-gray-300 text-sm min-w-40">
                    <option :value="null" disabled>{{ trans('Select type') }}</option>
                    <option v-for="option in data.type_options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Reference') }}</label>
                <input type="text" v-model="newReference" class="w-40 rounded border-gray-300 text-sm" />
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">{{ trans('Valid until') }}</label>
                <input type="date" v-model="newValidUntil" class="rounded border-gray-300 text-sm" />
            </div>
            <div class="flex items-center gap-1 pb-2">
                <input type="checkbox" v-model="newIsRequired" id="new-is-required" />
                <label for="new-is-required" class="text-xs text-gray-500">{{ trans('Required') }}</label>
            </div>
            <button
                type="button"
                class="rounded bg-indigo-600 text-white text-sm px-3 py-2 disabled:opacity-50"
                :disabled="!newType || processing"
                @click="store"
            >
                <FontAwesomeIcon :icon="['fal', 'plus']" fixed-width class="mr-1" />
                {{ trans('Add') }}
            </button>
        </div>
    </div>
</template>
