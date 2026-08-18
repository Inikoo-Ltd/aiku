<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Thu, 13 Aug 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { inject, ref } from "vue"
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
                        v-if="loadingLocations.includes(location.id)"
                        v-tooltip="trans('Setting as audited')"
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
                            :disabled="loadingLocations.includes(location.id)"
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
                        @click="() => submitNewQuantity(location)"
                        size="xs"
                    />
                </div>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
