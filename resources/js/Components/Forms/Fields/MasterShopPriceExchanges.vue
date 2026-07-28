<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 25 Jul 2026 15:20:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { reactive, ref, computed, onMounted, onUnmounted } from "vue"
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
    fraction_digits?: number
}

const props = defineProps<{
    fieldName: string
    fieldData: {
        value: Record<string, PriceExchange>
        currencies_shops?: Record<string, {
            shops: string[]
            number_products: number
            real_exchanges?: Record<string, number | null>
        }>
        master_shop_id?: number
        running_operations?: Record<string, Progress>
        updateRoute: routeType
    }
}>()

interface Progress {
    state: 'queued' | 'waiting' | 'updating_prices' | 'breaking_cache' | 'repricing_baskets' | 'finished' | 'failed'
    waiting_for?: string[]
    done: number
    total: number
    baskets_total?: number
    baskets_done?: number
    baskets_started_at?: string
    updating_started_at?: string
    started_at?: string
    error?: string
}

const operations = reactive<Record<string, Progress>>({ ...(props.fieldData.running_operations || {}) })
const progressModalCurrency = ref<string | null>(null)

const isRunning = (code: string) =>
    !!operations[code] && !['finished', 'failed'].includes(operations[code].state)

const progressPct = (progress: Progress) =>
    progress.total ? Math.round(progress.done / progress.total * 100) : 0

const remainingText = (done: number | undefined, total: number | undefined, startedAt: string | undefined) => {
    if (!total || !done || !startedAt) return null
    const elapsedMs = Date.now() - new Date(startedAt).getTime()
    if (elapsedMs <= 0) return null
    const remainingMs = elapsedMs * (total - done) / done
    const remainingMin = Math.ceil(remainingMs / 60000)
    return remainingMin <= 1 ? trans("less than a minute left") : trans(":min min left", { min: String(remainingMin) })
}

const etaText = (progress: Progress) => remainingText(progress.done, progress.total, progress.updating_started_at || progress.started_at)

const basketsPct = (progress: Progress) =>
    progress.baskets_total ? Math.round((progress.baskets_done || 0) / progress.baskets_total * 100) : 0

const basketsEtaText = (progress: Progress) =>
    remainingText(progress.baskets_done, progress.baskets_total, progress.baskets_started_at)

onMounted(() => {
    if (props.fieldData.master_shop_id && window.Echo) {
        window.Echo.private(`grp.master-shop.${props.fieldData.master_shop_id}`)
            .listen('.price-exchange-progress', (event: Progress & { currency: string }) => {
                const { currency, ...progress } = event
                operations[currency] = progress
            })
    }
})

onUnmounted(() => {
    if (props.fieldData.master_shop_id && window.Echo) {
        window.Echo.leave(`grp.master-shop.${props.fieldData.master_shop_id}`)
    }
})

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
            fraction_digits: exchangeData.fraction_digits ?? 2,
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
    if (parseExchange(row.exchange) === null) return trans("Cannot save: no valid exchange rate set")
    return null
}

const isDirty = (row: typeof rows[0]) =>
    JSON.stringify(rowPayload(row)) !== JSON.stringify(rowPayload({ ...JSON.parse(row.original), code: row.code }))

const parseExchange = (raw: unknown): number | null => {
    const str = String(raw ?? '').trim().replace(/\s/g, '')
    if (!str) return null
    const normalized = str.replace(',', '.')
    if ((normalized.match(/\./g) || []).length > 1 || !/^\d*\.?\d+$/.test(normalized)) return null
    const value = Number(normalized)
    return value > 0 ? value : null
}

const rowPayload = (row: { code: string, is_major: boolean, major?: string | null, exchange?: number | string | null, fraction_digits?: number }) =>
    row.is_major
        ? { currency: row.code, is_major: true }
        : { currency: row.code, is_major: false, major: row.major, exchange: parseExchange(row.exchange), fraction_digits: row.fraction_digits ?? 2 }

const rowIsValid = (row: typeof rows[0]) =>
    row.is_major || (row.major && majorCodes.value.includes(row.major) && parseExchange(row.exchange) !== null)

const affectedShops = (code: string) => props.fieldData.currencies_shops?.[code] ?? null

const realExchange = (row: typeof rows[0]) =>
    !row.is_major && row.major
        ? affectedShops(row.code)?.real_exchanges?.[row.major] ?? null
        : null

const realExchangeDiffPct = (row: typeof rows[0]) => {
    const real = realExchange(row)
    const parsed = parseExchange(row.exchange)
    if (!real || parsed === null) return null
    return (parsed / real - 1) * 100
}

const confirmingRow = ref<typeof rows[0] | null>(null)

const cancelAndRevert = () => {
    if (confirmingRow.value) {
        const original: PriceExchange = JSON.parse(confirmingRow.value.original)
        confirmingRow.value.is_major = original.is_major
        confirmingRow.value.major = original.major ?? null
        confirmingRow.value.exchange = original.exchange ?? null
    }
    confirmingRow.value = null
}
const isSaving = ref(false)

const majorChanged = (row: typeof rows[0]) => {
    const original: PriceExchange = JSON.parse(row.original)
    return !original.is_major && !row.is_major && original.major !== row.major
        ? { from: original.major, to: row.major }
        : null
}

const becomingMajor = (row: typeof rows[0]) => row.is_major && !(JSON.parse(row.original) as PriceExchange).is_major

const modalSeverityPct = (row: /* typeof rows[0] */ any): number => {
    const impact = exchangeChangePct(row)
    const deviation = realExchangeDiffPct(row)
    return Math.max(impact ? Math.abs(impact.pct) : 0, deviation !== null ? Math.abs(deviation) : 0)
}

const impactSeverityClass = (absPct: number) => {
    if (absPct < 2) return 'border-gray-300 bg-gray-50 text-gray-700'
    if (absPct < 4) return 'border-yellow-300 bg-yellow-50 text-yellow-700'
    if (absPct < 6) return 'border-orange-300 bg-orange-50 text-orange-700'
    return 'border-red-300 bg-red-50 text-red-700'
}

const impactSeverityTextClass = (absPct: number) => {
    if (absPct < 2) return 'text-gray-600'
    if (absPct < 4) return 'text-yellow-600 font-medium'
    if (absPct < 6) return 'text-orange-600 font-medium'
    return 'text-red-600 font-bold'
}

const exchangeChangePct = (row: typeof rows[0]): { pct: number, estimated: boolean } | null => {
    const original: PriceExchange = JSON.parse(row.original)
    if (row.is_major || parseExchange(row.exchange) === null) return null

    let ratio: number
    let estimated = false

    if (original.is_major || !original.exchange) {
        return null
    } else {
        ratio = (parseExchange(row.exchange) ?? 0) / Number(original.exchange)
        if (original.major !== row.major) {
            const realOld = affectedShops(row.code)?.real_exchanges?.[original.major || '']
            const realNew = affectedShops(row.code)?.real_exchanges?.[row.major || '']
            if (!realOld || !realNew) return null
            ratio *= realOld / realNew
            estimated = true
        }
    }

    const pct = (ratio - 1) * 100
    return Math.abs(pct) < 0.005 ? null : { pct, estimated }
}

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
                if (!row.is_major) {
                    operations[row.code] ??= { state: 'queued', done: 0, total: 0 }
                    progressModalCurrency.value = row.code
                }
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
                    <th class="py-2 pr-4">{{ trans("Decimals") }}</th>
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
                                :disabled="isRunning(row.code)"
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
                                :disabled="isRunning(row.code) || (row.is_major && followerCodes(row.code).length > 0)"
                                @click="setMinor(row)"
                            >
                                {{ trans("Minor") }}
                            </button>
                        </div>
                    </td>
                    <td class="py-2 pr-4">
                        <select v-if="!row.is_major" v-model="row.major" :disabled="isRunning(row.code)" class="rounded border-gray-300 text-sm py-1 disabled:opacity-50">
                            <option v-for="majorCode in majorCodes.filter(code => code !== row.code)" :key="majorCode" :value="majorCode">
                                {{ majorCode }}
                            </option>
                        </select>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="py-2 pr-4">
                        <input v-if="!row.is_major" v-model="row.exchange" type="text" inputmode="decimal"
                            :disabled="isRunning(row.code)"
                            class="rounded border-gray-300 text-sm py-1 w-28 tabular-nums disabled:opacity-50"
                            :class="{ 'border-red-400': row.exchange && parseExchange(row.exchange) === null }" />
                        <div v-if="!row.is_major && String(row.exchange ?? '').includes(',') && parseExchange(row.exchange) !== null"
                            class="text-[10px] text-gray-400 mt-0.5">
                            = {{ parseExchange(row.exchange) }}
                        </div>
                        <div v-else-if="!row.is_major && row.exchange && parseExchange(row.exchange) === null"
                            class="text-[10px] text-red-500 mt-0.5">
                            {{ trans("Invalid number") }}
                        </div>
                    </td>
                    <td class="py-2 pr-4">
                        <select v-if="!row.is_major" v-model.number="row.fraction_digits" :disabled="isRunning(row.code)"
                            class="rounded border-gray-300 text-sm py-1 disabled:opacity-50"
                            v-tooltip="trans('Whole numbers: converted prices are rounded up, e.g. 248.88 becomes 249')">
                            <option :value="2">{{ trans("0.00") }}</option>
                            <option :value="0">{{ trans("Whole numbers") }}</option>
                        </select>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="py-2 text-right">
                        <button v-if="isRunning(row.code)" type="button"
                            class="w-28 text-left cursor-pointer"
                            v-tooltip="trans('Updating prices, click for details')"
                            @click="progressModalCurrency = row.code">
                            <template v-if="operations[row.code].state === 'repricing_baskets'">
                                <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full bg-indigo-500 transition-all"
                                        :style="{ width: basketsPct(operations[row.code]) + '%' }" />
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    {{ trans('baskets') }} {{ basketsPct(operations[row.code]) }}%{{ basketsEtaText(operations[row.code]) ? ' · ~' + basketsEtaText(operations[row.code]) : '' }}
                                </div>
                            </template>
                            <template v-else>
                                <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                                    <div class="h-full bg-indigo-500 transition-all"
                                        :class="{ 'animate-pulse': !operations[row.code].total }"
                                        :style="{ width: operations[row.code].total ? progressPct(operations[row.code]) + '%' : '100%' }" />
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">
                                    {{ operations[row.code].total
                                        ? progressPct(operations[row.code]) + '%'
                                            + (etaText(operations[row.code]) ? ' · ~' + etaText(operations[row.code]) : '')
                                        : (['queued', 'waiting'].includes(operations[row.code].state) ? trans('waiting…') : trans('working…')) }}
                                </div>
                            </template>
                        </button>
                        <span v-else :class="{ invisible: !isDirty(row) }" v-tooltip="invalidReason(row)">
                            <Button
                                :label="trans('Apply…')"
                                size="xs"
                                :disabled="!rowIsValid(row)"
                                @click="confirmingRow = row"
                            />
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>

        <Modal :isOpen="!!progressModalCurrency" width="w-full max-w-lg" @close="progressModalCurrency = null">
            <div v-if="progressModalCurrency && operations[progressModalCurrency]">
                <div class="font-bold text-xl mb-4">
                    {{ trans("Updating :currency prices", { currency: progressModalCurrency }) }}
                </div>

                <div class="space-y-4 text-sm">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="font-medium">{{ trans("1. Changing product prices") }}</span>
                            <span class="tabular-nums text-gray-500">
                                {{ operations[progressModalCurrency].total
                                    ? operations[progressModalCurrency].done.toLocaleString() + ' / ' + operations[progressModalCurrency].total.toLocaleString()
                                        + ' (' + progressPct(operations[progressModalCurrency]) + '%)'
                                    : trans('starting…') }}
                            </span>
                        </div>
                        <div class="h-3 rounded-full bg-gray-200 overflow-hidden">
                            <div class="h-full bg-indigo-500 transition-all"
                                :class="{ 'animate-pulse': operations[progressModalCurrency].state === 'queued' }"
                                :style="{ width: operations[progressModalCurrency].total
                                    ? progressPct(operations[progressModalCurrency]) + '%'
                                    : '0%' }" />
                        </div>
                        <div v-if="operations[progressModalCurrency].state === 'updating_prices' && etaText(operations[progressModalCurrency])"
                            class="text-xs text-gray-400 mt-1 text-right">
                            ~{{ etaText(operations[progressModalCurrency]) }}
                        </div>
                        <div v-if="operations[progressModalCurrency].state === 'waiting'"
                            class="text-xs text-amber-600 mt-1 text-right">
                            {{ trans("Waiting for the :currencies price update to finish, will start automatically…", {
                                currencies: (operations[progressModalCurrency].waiting_for || []).join(', ')
                            }) }}
                        </div>
                        <div v-else-if="operations[progressModalCurrency].state === 'updating_prices' && !operations[progressModalCurrency].done"
                            class="text-xs text-gray-400 mt-1 text-right">
                            {{ trans("starting…") }}
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1"
                            :class="operations[progressModalCurrency].state === 'finished' ? 'text-emerald-600'
                                : operations[progressModalCurrency].state === 'repricing_baskets' ? 'text-gray-700'
                                : 'text-gray-400'">
                            <span>
                                {{ operations[progressModalCurrency].state === 'finished' ? '✓' : '○' }}
                                {{ trans("2. Repricing basket orders") }}
                            </span>
                            <span v-if="operations[progressModalCurrency].baskets_total !== undefined" class="tabular-nums text-gray-500">
                                {{ (operations[progressModalCurrency].baskets_done || 0).toLocaleString() }} /
                                {{ operations[progressModalCurrency].baskets_total.toLocaleString() }}
                                ({{ basketsPct(operations[progressModalCurrency]) }}%)
                            </span>
                        </div>
                        <template v-if="['repricing_baskets', 'finished'].includes(operations[progressModalCurrency].state)">
                            <div class="h-3 rounded-full bg-gray-200 overflow-hidden">
                                <div class="h-full bg-indigo-500 transition-all"
                                    :style="{ width: basketsPct(operations[progressModalCurrency]) + '%' }" />
                            </div>
                            <div v-if="operations[progressModalCurrency].state === 'repricing_baskets' && basketsEtaText(operations[progressModalCurrency])"
                                class="text-xs text-gray-400 mt-1 text-right">
                                ~{{ basketsEtaText(operations[progressModalCurrency]) }}
                            </div>
                        </template>
                    </div>

                    <div v-if="operations[progressModalCurrency].state === 'finished'"
                        class="rounded border border-emerald-300 bg-emerald-50 text-emerald-700 px-3 py-2 font-medium">
                        {{ trans("Done! All prices updated.") }}
                    </div>
                    <div v-else-if="operations[progressModalCurrency].state === 'failed'"
                        class="rounded border border-red-300 bg-red-50 text-red-700 px-3 py-2">
                        <span class="font-medium">{{ trans("Something went wrong.") }}</span>
                        {{ operations[progressModalCurrency].error }}
                    </div>
                    <p v-else class="text-gray-500 text-xs">
                        {{ trans("This can take several minutes. You can close this window, the update continues in the background.") }}
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <Button :label="trans('Close')" type="tertiary" @click="progressModalCurrency = null" />
                </div>
            </div>
        </Modal>

        <Modal :isOpen="!!confirmingRow" width="w-full max-w-lg" @close="confirmingRow = null">
            <div v-if="confirmingRow">
                <div v-if="becomingMajor(confirmingRow)" class="font-bold text-xl mb-3">
                    {{ trans(":currency will become a major currency", { currency: confirmingRow.code }) }}
                </div>
                <div v-else class="font-bold text-xl mb-3 text-amber-600">
                    ⚠️
                    <template v-if="affectedShops(confirmingRow.code)">
                        {{ affectedShops(confirmingRow.code)!.shops.length === 1
                            ? trans("This will change the price of all :count products in shop :shop", {
                                count: affectedShops(confirmingRow.code)!.number_products.toLocaleString(),
                                shop: affectedShops(confirmingRow.code)!.shops[0]
                            })
                            : trans("This will change the price of all :count products in :n shops: :shops", {
                                count: affectedShops(confirmingRow.code)!.number_products.toLocaleString(),
                                n: String(affectedShops(confirmingRow.code)!.shops.length),
                                shops: affectedShops(confirmingRow.code)!.shops.join(', ')
                            })
                        }}
                    </template>
                    <template v-else>
                        {{ trans("This will change prices in whole shops") }}
                    </template>
                </div>
                <div class="text-sm space-y-2">
                    <p v-if="!confirmingRow.is_major">
                        {{ trans(":currency will follow :major with exchange 1 :major = :exchange :currency.", {
                            currency: confirmingRow.code,
                            major: confirmingRow.major || '',
                            exchange: String(parseExchange(confirmingRow.exchange))
                        }) }}
                    </p>
                    <template v-if="!confirmingRow.is_major">
                        <p v-if="confirmingRow.fraction_digits === 0" class="font-medium">
                            {{ trans(":currency prices will be whole numbers: converted prices are rounded up, e.g. 248.88 becomes 249.", { currency: confirmingRow.code }) }}
                        </p>
                        <p v-if="majorChanged(confirmingRow)" class="font-medium">
                            {{ trans(":currency will stop following :from and will follow :to instead.", {
                                currency: confirmingRow.code,
                                from: majorChanged(confirmingRow)!.from || '',
                                to: majorChanged(confirmingRow)!.to || ''
                            }) }}
                        </p>
                        <div v-if="exchangeChangePct(confirmingRow)"
                            class="rounded border px-3 py-2 font-medium"
                            :class="impactSeverityClass(Math.abs(exchangeChangePct(confirmingRow)!.pct))">
                            {{ exchangeChangePct(confirmingRow)!.pct > 0
                                ? trans("All :currency prices will INCREASE by approximately :pct%", { currency: confirmingRow.code, pct: exchangeChangePct(confirmingRow)!.pct.toFixed(1) })
                                : trans("All :currency prices will DECREASE by approximately :pct%", { currency: confirmingRow.code, pct: Math.abs(exchangeChangePct(confirmingRow)!.pct).toFixed(1) })
                            }}
                            <span v-if="exchangeChangePct(confirmingRow)!.estimated" class="font-normal text-xs opacity-75">
                                ({{ majorChanged(confirmingRow)
                                    ? trans("estimated: assumes :from and :to prices track the market rate", {
                                        from: majorChanged(confirmingRow)!.from || '',
                                        to: majorChanged(confirmingRow)!.to || ''
                                    })
                                    : trans("estimated against today's market rate")
                                }})
                            </span>
                        </div>
                        <div v-if="realExchange(confirmingRow)"
                            class="rounded border px-3 py-2 space-y-0.5"
                            :class="realExchangeDiffPct(confirmingRow) !== null
                                ? impactSeverityClass(Math.abs(realExchangeDiffPct(confirmingRow)!))
                                : 'border-gray-200 bg-gray-50'">
                            <div class="text-gray-600">
                                {{ trans("Real exchange today: 1 :major = :rate :currency", {
                                    major: confirmingRow.major || '',
                                    rate: realExchange(confirmingRow)!.toLocaleString(undefined, { maximumFractionDigits: 4 }),
                                    currency: confirmingRow.code
                                }) }}
                            </div>
                            <div v-if="realExchangeDiffPct(confirmingRow) !== null" class="font-medium">
                                {{ realExchangeDiffPct(confirmingRow)! >= 0
                                    ? trans("Your rate is :pct% ABOVE the real exchange", { pct: realExchangeDiffPct(confirmingRow)!.toFixed(1) })
                                    : trans("Your rate is :pct% BELOW the real exchange", { pct: Math.abs(realExchangeDiffPct(confirmingRow)!).toFixed(1) })
                                }}
                            </div>
                        </div>
                        <ul class="list-disc pl-5 space-y-1 text-gray-600">
                            <li>{{ trans("Every product price in every shop using :currency will be recalculated.", { currency: confirmingRow.code }) }}</li>
                            <li>{{ trans("Orders currently in customers' baskets will be repriced too.") }}</li>
                            <li>{{ trans("This runs in the background and can take several minutes to complete.") }}</li>
                        </ul>
                    </template>
                    <template v-else>
                        <p class="font-medium text-emerald-700">
                            {{ trans("No prices will change now.") }}
                        </p>
                        <p>
                            {{ trans(":currency prices will stop following an exchange rate. From now on, when changing prices in the Masters section, you must provide the :currency price yourself.", { currency: confirmingRow.code }) }}
                        </p>
                    </template>
                </div>
                <div class="mt-6 flex gap-2 justify-end">
                    <Button :label="trans('Cancel')" type="tertiary" @click="cancelAndRevert" />
                    <Button
                        :label="becomingMajor(confirmingRow) ? trans('Yes, make it major') : trans('Yes, update prices')"
                        :type="becomingMajor(confirmingRow) ? 'primary' : 'negative'"
                        :loading="isSaving"
                        @click="save"
                    />
                </div>
            </div>
        </Modal>
    </div>
</template>
