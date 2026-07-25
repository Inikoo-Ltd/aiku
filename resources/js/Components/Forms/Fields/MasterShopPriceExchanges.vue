<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 25 Jul 2026 15:20:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { reactive, ref, computed } from "vue"
import { router } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"

interface PriceExchange {
    is_major: boolean
    major?: string | null
    exchange?: number | null
}

const props = defineProps<{
    fieldName: string
    fieldData: {
        value: Record<string, PriceExchange>
        updateRoute: routeType
    }
}>()

const rows = reactive(
    Object.entries(props.fieldData.value || {})
        .sort(([codeA, a], [codeB, b]) =>
            Number(b.is_major) - Number(a.is_major) || codeA.localeCompare(codeB)
        )
        .map(([code, exchangeData]) => ({
            code,
            is_major: exchangeData.is_major,
            major: exchangeData.major ?? null,
            exchange: exchangeData.exchange ?? null,
            original: JSON.stringify(exchangeData),
        }))
)

const majorCodes = computed(() => rows.filter(row => row.is_major).map(row => row.code))

const followerCodes = (code: string) =>
    rows.filter(row => !row.is_major && row.major === code).map(row => row.code)

const setMinor = (row: typeof rows[0]) => {
    row.is_major = false
    const options = majorCodes.value.filter(code => code !== row.code)
    if (!row.major || !options.includes(row.major)) {
        row.major = options.length === 1 ? options[0] : null
    }
}

const invalidReason = (row: typeof rows[0]) => {
    if (row.is_major) return null
    if (!row.major) return trans("Select a major currency to follow")
    if (!(Number(row.exchange) > 0)) return trans("Cannot save: no exchange rate set")
    return null
}

const isDirty = (row: typeof rows[0]) =>
    JSON.stringify(rowPayload(row)) !== JSON.stringify(rowPayload({ ...JSON.parse(row.original), code: row.code }))

const rowPayload = (row: { code: string, is_major: boolean, major?: string | null, exchange?: number | null }) =>
    row.is_major
        ? { currency: row.code, is_major: true }
        : { currency: row.code, is_major: false, major: row.major, exchange: Number(row.exchange) }

const rowIsValid = (row: typeof rows[0]) =>
    row.is_major || (row.major && majorCodes.value.includes(row.major) && Number(row.exchange) > 0)

const confirmingRow = ref<typeof rows[0] | null>(null)
const isSaving = ref(false)

const save = () => {
    if (!confirmingRow.value) return
    const row = confirmingRow.value

    router.patch(
        route(props.fieldData.updateRoute.name, props.fieldData.updateRoute.parameters),
        rowPayload(row),
        {
            preserveScroll: true,
            onStart: () => isSaving.value = true,
            onSuccess: () => {
                row.original = JSON.stringify(rowPayload(row))
                confirmingRow.value = null
                notify({
                    title: trans("Success"),
                    text: trans("Currency :currency updated. Prices are being recalculated in the background.", { currency: row.code }),
                    type: "success"
                })
            },
            onError: (errors) => {
                notify({
                    title: trans("Something went wrong"),
                    text: Object.values(errors).join(", "),
                    type: "error"
                })
            },
            onFinish: () => isSaving.value = false,
        }
    )
}
</script>

<template>
    <div class="w-full max-w-2xl">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-400 border-b">
                    <th class="py-2 pr-4">{{ trans("Currency") }}</th>
                    <th class="py-2 pr-4">{{ trans("Role") }}</th>
                    <th class="py-2 pr-4">{{ trans("Follows") }}</th>
                    <th class="py-2 pr-4">{{ trans("Exchange") }}</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.code" class="border-b border-gray-100">
                    <td class="py-2 pr-4 font-medium">{{ row.code }}</td>
                    <td class="py-2 pr-4">
                        <div class="inline-flex rounded-full border border-gray-300 bg-gray-100 p-0.5 text-xs select-none">
                            <button
                                type="button"
                                class="px-3 py-1 rounded-full transition-colors"
                                :class="row.is_major ? 'bg-indigo-600 text-white font-medium shadow-sm' : 'text-gray-300 hover:text-gray-500'"
                                @click="row.is_major = true"
                            >
                                {{ trans("Major") }}
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1 rounded-full transition-colors"
                                :class="[
                                    !row.is_major ? 'bg-indigo-600 text-white font-medium shadow-sm' : 'text-gray-300 hover:text-gray-500',
                                    row.is_major && followerCodes(row.code).length ? 'opacity-40 cursor-not-allowed hover:text-gray-300' : ''
                                ]"
                                v-tooltip="row.is_major && followerCodes(row.code).length
                                    ? trans('Cannot be minor: :followers follow it', { followers: followerCodes(row.code).join(', ') })
                                    : undefined"
                                :disabled="row.is_major && followerCodes(row.code).length > 0"
                                @click="setMinor(row)"
                            >
                                {{ trans("Minor") }}
                            </button>
                        </div>
                    </td>
                    <td class="py-2 pr-4">
                        <select v-if="!row.is_major" v-model="row.major" class="rounded border-gray-300 text-sm py-1">
                            <option v-for="majorCode in majorCodes.filter(code => code !== row.code)" :key="majorCode" :value="majorCode">
                                {{ majorCode }}
                            </option>
                        </select>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="py-2 pr-4">
                        <input v-if="!row.is_major" v-model="row.exchange" type="number" min="0" step="any"
                            class="rounded border-gray-300 text-sm py-1 w-28 tabular-nums" />
                        <span v-else class="text-gray-400">{{ trans("Set manually") }}</span>
                    </td>
                    <td class="py-2 text-right">
                        <span v-if="isDirty(row)" v-tooltip="invalidReason(row)">
                            <Button
                                :label="trans('Save')"
                                size="xs"
                                :disabled="!rowIsValid(row)"
                                @click="confirmingRow = row"
                            />
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <Modal :isOpen="!!confirmingRow" width="w-full max-w-lg" @close="confirmingRow = null">
            <div v-if="confirmingRow">
                <div class="font-bold text-xl mb-3 text-amber-600">
                    ⚠️ {{ trans("This will change prices in whole shops") }}
                </div>
                <div class="text-sm space-y-2">
                    <p v-if="!confirmingRow.is_major">
                        {{ trans(":currency will follow :major with exchange 1 :major = :exchange :currency.", {
                            currency: confirmingRow.code,
                            major: confirmingRow.major || '',
                            exchange: String(confirmingRow.exchange)
                        }) }}
                    </p>
                    <p v-if="!confirmingRow.is_major">
                        {{ trans("The prices of every product in every shop using :currency will be recalculated in the background. This affects hundreds or thousands of live prices.", { currency: confirmingRow.code }) }}
                    </p>
                    <p v-else>
                        {{ trans(":currency will become a major currency: its prices will no longer follow an exchange rate and must be edited manually per product.", { currency: confirmingRow.code }) }}
                    </p>
                </div>
                <div class="mt-6 flex gap-2 justify-end">
                    <Button :label="trans('Cancel')" type="tertiary" @click="confirmingRow = null" />
                    <Button
                        :label="trans('Yes, update prices')"
                        type="negative"
                        :loading="isSaving"
                        @click="save"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
