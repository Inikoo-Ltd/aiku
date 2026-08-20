<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Thu, 13 Aug 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { inject, ref, onBeforeUnmount } from "vue"
import { debounce } from "lodash-es"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { RouteParams } from "@/types/route-params"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDotCircle } from "@fal"
import { faDotCircle as fasDotCircle } from "@fas"
import { InputNumber } from "primevue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import {
    useLowStockAuditBroadcast,
    LowStockAuditedEvent,
} from "@/Composables/useLowStockAuditBroadcast"

library.add(faDotCircle, fasDotCircle)

const props = defineProps<{
    data: object
    tab?: string
    auditRoute?: routeType
}>()

const locale = inject("locale", aikuLocaleStructure)
const routeParams = route().params as RouteParams

interface LowStockAuditLocation {
    id: number
    code: string
    quantity: number
    audited_at: string | null
    is_low_stock_checked: boolean
}

interface LowStockAudit {
    id: number
    slug: string
    code: string
    name: string
    family_code: string | null
    family_slug: string | null
    stock: number
    locations: LowStockAuditLocation[]
}

function orgStockRoute(lowStockAudit: LowStockAudit) {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
        routeParams.organisation,
        routeParams.warehouse,
        lowStockAudit.slug,
    ])
}

function familyRoute(slug: string) {
    return route("grp.org.warehouses.show.inventory.org_stock_families.show", [
        routeParams.organisation,
        routeParams.warehouse,
        slug,
    ])
}

const loadingLocations = ref<number[]>([])
const newQuantities = ref<Record<number, number | null>>({})

// Auditing with no quantity keeps the stock as it is, auditing with one recounts it. Both
// stamp audited_at and is_low_stock_checked, so the row leaves the list either way.
function auditLocation(location: LowStockAuditLocation, quantity: number | null = null) {
    if (loadingLocations.value.includes(location.id)) {
        return
    }

    router[props.auditRoute?.method || "patch"](
        route(props.auditRoute?.name, { locationOrgStock: location.id }),
        quantity === null ? {} : { quantity },
        {
            preserveScroll: true,
            onStart: () => {
                loadingLocations.value.push(location.id)
            },
            onSuccess: () => {
                newQuantities.value[location.id] = null
                releaseTypingLock(location)
                notify({
                    title: trans("Success"),
                    text: trans("Successfully audited stock location (:xlocation)", {
                        xlocation: location.code,
                    }),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to audit the stock location"),
                    type: "error",
                })
            },
            onFinish: () => {
                loadingLocations.value = loadingLocations.value.filter((id) => id !== location.id)
            },
        }
    )
}

function reloadLowStockAudits() {
    router.reload({ only: ["lowStockAudits"] })
}

const debouncedReload = debounce(reloadLowStockAudits, 600)

const findLocation = (locationOrgStockId: number): LowStockAuditLocation | undefined =>
    ((props.data as any)?.data ?? [])
        .flatMap((row: LowStockAudit) => row.locations ?? [])
        .find((row: LowStockAuditLocation) => row.id === locationOrgStockId)

const { isLocked, isOrgStockLocked, announceLock } =
    useLowStockAuditBroadcast({
        onAudited: (event: LowStockAuditedEvent) => {
            const location = findLocation(event.location_org_stock_id)

            // Patch what is on screen straight away, then let the reload drop finished rows
            if (location) {
                location.quantity = event.quantity ?? location.quantity
                location.audited_at = event.audited_at ?? location.audited_at
                location.is_low_stock_checked =
                    event.is_low_stock_checked ?? location.is_low_stock_checked
            }

            debouncedReload()
        },
    })

const findOrgStockId = (locationOrgStockId: number): number | undefined =>
    ((props.data as any)?.data ?? []).find((row: LowStockAudit) =>
        (row.locations ?? []).some(
            (location: LowStockAuditLocation) => location.id === locationOrgStockId
        )
    )?.id

// Locked by another tab, by the audit modal being open on this SKO, or by this tab's own request
const isLocationBusy = (location: LowStockAuditLocation) => {
    const orgStockId = findOrgStockId(location.id)

    return (
        isLocked(location.id) ||
        (orgStockId !== undefined && isOrgStockLocked(orgStockId)) ||
        loadingLocations.value.includes(location.id)
    )
}

// Typing a quantity is the intent to count, so the detail view is held shut from the first
// keystroke rather than from the save. It is let go once the field is emptied or goes quiet.
const typingLocks = ref<number[]>([])

const setTypingLock = (location: LowStockAuditLocation, isLocking: boolean) => {
    const orgStockId = findOrgStockId(location.id)

    if (orgStockId === undefined) {
        return
    }

    if (isLocking === typingLocks.value.includes(location.id)) {
        return
    }

    typingLocks.value = isLocking
        ? [...typingLocks.value, location.id]
        : typingLocks.value.filter((id) => id !== location.id)

    announceLock({
        org_stock_id: orgStockId,
        location_org_stock_id: location.id,
        is_locked: isLocking,
        source: "list",
    })
}

const releaseTypingLock = (location: LowStockAuditLocation) => setTypingLock(location, false)

const hasQuantity = (quantity: unknown) =>
    quantity !== null && quantity !== undefined && quantity !== ""

// One debounce per location: a shared one would let a second field cancel the first's release
const releaseTimers: Record<number, ReturnType<typeof setTimeout>> = {}

const scheduleReleaseIfEmpty = (location: LowStockAuditLocation) => {
    clearTimeout(releaseTimers[location.id])

    releaseTimers[location.id] = setTimeout(() => {
        if (!hasQuantity(newQuantities.value[location.id])) {
            releaseTypingLock(location)
        }
    }, 1500)
}

// The value comes off the event, not off newQuantities: PrimeVue fires input before v-model
// has written the ref, so reading it here would miss the very first keystroke.
const onQuantityTyping = (location: LowStockAuditLocation, value: unknown) => {
    setTypingLock(location, hasQuantity(value))
    scheduleReleaseIfEmpty(location)
}

const onQuantityBlur = (location: LowStockAuditLocation) => {
    setTypingLock(location, hasQuantity(newQuantities.value[location.id]))
}

onBeforeUnmount(() => {
    Object.values(releaseTimers).forEach((timer) => clearTimeout(timer))
})

function submitNewQuantity(location: LowStockAuditLocation) {
    const quantity = newQuantities.value[location.id]

    if (quantity === null || quantity === undefined) {
        return
    }

    auditLocation(location, Number(quantity))
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'low_stock_audits'">
        <template #cell(code)="{ item: lowStockAudit }">
            <Link :href="orgStockRoute(lowStockAudit) as string" class="primaryLink">
                {{ lowStockAudit.code }}
            </Link>
        </template>

        <template #cell(family_code)="{ item: lowStockAudit }">
            <Link
                v-if="lowStockAudit.family_slug"
                :href="familyRoute(lowStockAudit.family_slug) as string"
                class="primaryLink"
            >
                {{ lowStockAudit.family_code }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(stock)="{ item: lowStockAudit }">
            <span class="tabular-nums">{{ locale.number(Number(lowStockAudit.stock)) }}</span>
        </template>

        <template #cell(locations)="{ item: lowStockAudit }">
            <div v-if="lowStockAudit.locations?.length" class="flex flex-col gap-y-1 min-w-80">
                <div
                    v-for="location in lowStockAudit.locations"
                    :key="location.id"
                    class="flex items-center gap-x-2"
                >
                    <div class="w-28 truncate" :title="location.code">
                        {{ location.code }}
                    </div>

                    <div class="w-16 text-right tabular-nums text-gray-500">
                        {{ locale.number(Number(location.quantity)) }}
                    </div>

                    <div
                        v-if="isLocationBusy(location)"
                        v-tooltip="
                            loadingLocations.includes(location.id)
                                ? trans('Setting as audited')
                                : trans('Being audited somewhere else')
                        "
                        class="text-gray-400"
                    >
                        <LoadingIcon />
                    </div>

                    <div
                        v-else
                        v-tooltip="
                            location.audited_at
                                ? trans('Last audit :date, mark as audited again with the same stock', {
                                      date: useFormatTime(new Date(location.audited_at)),
                                  })
                                : trans('Mark as audited with the same stock')
                        "
                        @click="() => auditLocation(location)"
                        class="cursor-pointer text-gray-400 hover:text-green-500"
                    >
                        <FontAwesomeIcon
                            :icon="location.audited_at ? 'fas fa-dot-circle' : 'fal fa-dot-circle'"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>

                    <div class="w-28">
                        <InputNumber
                            v-model="newQuantities[location.id]"
                            v-tooltip="trans('Set a new stock quantity for this location')"
                            :placeholder="trans('New qty')"
                            :min="0"
                            :step="1"
                            :disabled="isLocationBusy(location)"
                            @input="(event: { value: any }) => onQuantityTyping(location, event?.value)"
                            @blur="() => onQuantityBlur(location)"
                            @keyup.enter="() => submitNewQuantity(location)"
                            size="small"
                            fluid
                            inputClass="!py-0"
                        />
                    </div>

                    <Button
                        v-if="newQuantities[location.id] !== null && newQuantities[location.id] !== undefined"
                        v-tooltip="trans('Save the new quantity and mark as audited')"
                        :type="'save'"
                        :loading="loadingLocations.includes(location.id)"
                        :disabled="isLocationBusy(location)"
                        @click="() => submitNewQuantity(location)"
                        size="xs"
                    />
                </div>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
