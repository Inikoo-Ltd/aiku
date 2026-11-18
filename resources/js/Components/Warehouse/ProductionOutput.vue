<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Nov 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { reactive, ref, watch } from "vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Popover from "@/Components/Popover.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHandsHelping, faLock, faChevronDown, faCheck, faSave, faClipboardListCheck, faIndustry } from "@fal"

library.add(faHandsHelping, faLock, faChevronDown, faCheck, faSave, faClipboardListCheck, faIndustry)

type ProductionDestination = { type: "partner" | "stock", label: string, quantity: number, location_code: string | null, locations: { code: string, quantity: number }[], item_ids: number[], job_order_ids: number[] }
type ProductionItem = {
    id: number
    job_order_id: number
    job_order_reference: string
    artisan: string | null
    code: string
    name: string
    quantity_made: number
    quantity_total: number
    in_progress: boolean
    destinations: ProductionDestination[]
}

const props = defineProps<{
    resource: { data: ProductionItem[] }
    canEdit: boolean
    putAwayRoute: { name: string; parameters: Record<string, string> } | null
}>()

const destinationKey = (item: ProductionItem, destination: ProductionDestination) => item.id + "-" + (destination.type === "stock" ? "stock" : destination.location_code)
const putAwayLocation = reactive<Record<string, string>>({})
watch(() => props.resource, output => {
    output?.data.forEach(item => item.destinations.forEach(destination => putAwayLocation[destinationKey(item, destination)] ??= destination.location_code ?? ""))
}, { immediate: true })

const stockInLocation = (item: ProductionItem, destination: ProductionDestination) => destination.locations.find(location => location.code === putAwayLocation[destinationKey(item, destination)]?.toUpperCase())?.quantity
const putAwayQuantity = reactive<Record<string, number>>({})
const isPuttingAway = ref(false)
const putAwayError = ref<string | null>(null)

function putAway(item: ProductionItem, destination: ProductionDestination, quantity: number | null = null, allowNewLocations = false) {
    const key = destinationKey(item, destination)
    if (!props.putAwayRoute || isPuttingAway.value || !putAwayLocation[key]) return
    putAwayError.value = null
    router.post(route(props.putAwayRoute.name, props.putAwayRoute.parameters), {
        item_locations: Object.fromEntries(destination.item_ids.map(itemId => [itemId, putAwayLocation[key]])),
        job_order_ids: destination.job_order_ids,
        ...(quantity ? { item_quantities: { [item.id]: quantity } } : {}),
        allow_new_locations: allowNewLocations,
    }, {
        preserveScroll: true,
        onStart: () => isPuttingAway.value = true,
        onFinish: () => isPuttingAway.value = false,
        onSuccess: () => delete putAwayQuantity[key],
        onError: errors => {
            if (errors.new_location && window.confirm(errors.new_location + ". " + ctrans("Add this location to the stock?"))) {
                isPuttingAway.value = false
                putAway(item, destination, quantity, true)
            } else if (!errors.new_location) {
                putAwayError.value = Object.values(errors)[0] as string
            }
        },
    })
}
</script>

<template>
    <div>
        <div v-if="putAwayError" class="mx-4 mt-4 rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
            {{ putAwayError }}
        </div>
            <Table :resource="resource" name="production_output">
            <template #cell(job_order_reference)="{ item }">
                <div class="font-mono">{{ item.job_order_reference }}</div>
                <div v-if="item.in_progress" class="text-xs tabular-nums text-amber-700">
                    {{ item.quantity_made }}/{{ item.quantity_total }} · {{ ctrans("still in progress") }}
                </div>
                <div v-else class="text-xs tabular-nums text-gray-500">{{ item.quantity_total }}</div>
            </template>
            <template #cell(code)="{ item }">
                <span class="font-medium">{{ item.code }}</span>
            </template>
            <template #cell(actions)="{ item }">
                <div v-for="destination in item.destinations" :key="destinationKey(item, destination)" class="flex items-center justify-between gap-x-6 py-0.5">
                    <div>
                        <div v-if="destination.type === 'partner'" class="flex items-center gap-x-2 font-mono text-base" v-tooltip="ctrans('Goods out bay of partner :partner', { partner: destination.label })">
                            <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-hands-helping" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></span>
                            {{ putAwayLocation[destinationKey(item, destination)] }}
                        </div>
                        <div v-else-if="destination.locations.length === 1" class="flex items-center gap-x-2 font-mono text-base" v-tooltip="ctrans('The only location of this stock')">
                            <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-lock" class="text-gray-400" fixed-width aria-hidden="true" /></span>
                            {{ putAwayLocation[destinationKey(item, destination)] }}
                        </div>
                        <div v-else-if="destination.locations.length > 1" class="relative w-fit">
                            <Popover position="left-0" width="w-64">
                                <template #button>
                                    <div class="flex items-center gap-x-2 font-mono text-base hover:underline" v-tooltip="ctrans('This stock has :count locations, choose one', { count: String(destination.locations.length) })">
                                        <span class="inline-flex w-6 shrink-0 justify-center"><FontAwesomeIcon icon="fal fa-chevron-down" class="text-gray-400" fixed-width aria-hidden="true" /></span>
                                        {{ putAwayLocation[destinationKey(item, destination)] }}
                                    </div>
                                </template>
                                <template #content="{ close }">
                                    <div class="-mx-4 -my-3 divide-y divide-gray-100">
                                        <button v-for="location in destination.locations" :key="location.code" type="button"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-[var(--app-accent-soft)] focus:outline-none"
                                            :class="putAwayLocation[destinationKey(item, destination)] === location.code ? 'bg-[var(--app-accent-soft)]' : ''"
                                            @click="putAwayLocation[destinationKey(item, destination)] = location.code; close()">
                                            <span class="flex items-center gap-x-2">
                                                <FontAwesomeIcon icon="fal fa-check" fixed-width :class="putAwayLocation[destinationKey(item, destination)] === location.code ? 'text-[var(--app-accent)]' : 'text-transparent'" aria-hidden="true" />
                                                <span class="font-mono text-base font-semibold">{{ location.code }}</span>
                                            </span>
                                            <span class="text-xs tabular-nums text-gray-500">{{ ctrans("stock in location") }}: {{ location.quantity }}</span>
                                        </button>
                                    </div>
                                </template>
                            </Popover>
                        </div>
                        <input v-else v-model.trim="putAwayLocation[destinationKey(item, destination)]" type="text" :placeholder="ctrans('Location code')" :disabled="!canEdit"
                            class="w-32 rounded py-0.5 font-mono text-base uppercase" :class="putAwayLocation[destinationKey(item, destination)] ? 'border-gray-300' : 'border-amber-400 bg-amber-50'" />
                        <div class="pl-8 text-xs tabular-nums" :class="destination.type === 'partner' ? 'text-[var(--app-accent-strong)]' : putAwayLocation[destinationKey(item, destination)] ? 'text-gray-500' : 'text-amber-700'">
                            <template v-if="destination.type === 'partner'">{{ ctrans("Partner bay") }} · {{ destination.label }}</template>
                            <template v-else-if="!putAwayLocation[destinationKey(item, destination)]">{{ ctrans("No location yet, type one") }}</template>
                            <template v-else-if="stockInLocation(item, destination) !== undefined">({{ ctrans("stock in location") }}: {{ stockInLocation(item, destination) }})</template>
                            <template v-else>({{ ctrans("new location for this stock") }})</template>
                        </div>
                    </div>
                    <NumberWithButtonSave v-if="canEdit" :key="destinationKey(item, destination) + '-' + destination.quantity" :modelValue="0" noUndoButton noSaveButton
                        :readonly="!putAwayLocation[destinationKey(item, destination)] || isPuttingAway" :bindToTarget="{ step: 1, min: 0, max: destination.quantity }"
                        @update:modelValue="(quantity: number) => putAwayQuantity[destinationKey(item, destination)] = quantity">
                        <template #suffix>
                            <div class="ml-1 flex items-center gap-x-1">
                                <Button v-if="putAwayQuantity[destinationKey(item, destination)] > 0" type="primary" size="xs" icon="fal fa-save" :loading="isPuttingAway"
                                    v-tooltip="ctrans('Put away :quantity', { quantity: String(putAwayQuantity[destinationKey(item, destination)]) })"
                                    @click="putAway(item, destination, putAwayQuantity[destinationKey(item, destination)])" />
                                <Button type="secondary" size="xs" icon="fal fa-clipboard-list-check" :label="String(destination.quantity)" :loading="isPuttingAway" :disabled="!putAwayLocation[destinationKey(item, destination)]"
                                    v-tooltip="ctrans('Put away all :quantity in :location', { quantity: String(destination.quantity), location: putAwayLocation[destinationKey(item, destination)] || '-' })"
                                    @click="putAway(item, destination)" />
                            </div>
                        </template>
                    </NumberWithButtonSave>
                    <span v-else class="font-semibold tabular-nums">{{ destination.quantity }}</span>
                </div>
            </template>
        </Table>
    </div>
</template>
