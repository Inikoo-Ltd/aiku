<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import Image from "@common/Components/Image.vue"
import ModalPartnerStockList from "@/Components/Procurement/ModalPartnerStockList.vue"
import ModalAutoFillShoppingList from "@/Components/Procurement/ModalAutoFillShoppingList.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    orgPartner: { id: number, slug: string, currency: string }
    orgStockFetchRoute: { name: string, parameters: object }
}>()

const isModalOpen = ref(false)
const isAutoFillOpen = ref(false)

watch(isModalOpen, (isOpen, wasOpen) => {
    if (wasOpen && !isOpen) {
        router.reload({ only: ["data"] })
    }
})

const amountOf = (item: { quantity: number, price_per_sko: number | null }) =>
    Number(item.quantity) * Number(item.price_per_sko ?? 0)

const priorities = ["low", "normal", "high", "urgent"]

function updateItem(item: { id: number }, data: Record<string, string | null>) {
    router.patch(
        route("grp.org.procurement.org_partners.show.shopping_list.update", [
            route().params["organisation"],
            props.orgPartner.id,
            item.id,
        ]),
        data,
        { preserveScroll: true }
    )
}

function deleteItem(item: { id: number }) {
    router.delete(
        route("grp.org.procurement.org_partners.show.shopping_list.destroy", [
            route().params["organisation"],
            props.orgPartner.id,
            item.id,
        ]),
        { preserveScroll: true }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button type="secondary" icon="fal fa-magic" :label="trans('Auto-fill')" @click="isAutoFillOpen = true" />
            <Button type="create" :label="trans('Add stocks')" @click="isModalOpen = true" />
        </template>
    </PageHeading>

    <ModalPartnerStockList v-model="isModalOpen" :fetchRoute="orgStockFetchRoute" />
    <ModalAutoFillShoppingList v-model="isAutoFillOpen" :orgPartnerId="orgPartner.id" :currency="orgPartner.currency" />

    <Table :resource="data" class="mt-5">
        <template #cell(info)="{ item }">
            <div class="flex items-start gap-3">
                <div class="h-12 w-12 shrink-0 rounded">
                    <Image :src="item.image_sources" />
                </div>
                <div class="min-w-0 text-xs leading-5">
                    <div class="truncate text-sm font-medium text-gray-800">{{ item.org_stock_name }}</div>
                    <div class="text-gray-500">
                        {{ trans("Their stock") }} <b class="font-medium text-gray-700 tabular-nums">{{ item.their_available !== null ? useLocaleStore().number(Math.floor(Number(item.their_available))) : "-" }}</b>
                        · {{ trans("our stock") }} <b class="font-medium text-gray-700 tabular-nums">{{ useLocaleStore().number(Math.floor(Number(item.buyer_available ?? 0))) }}</b>
                        <template v-if="item.days_of_cover !== null">
                            ·
                            <span :class="{ 'text-red-600 font-medium': Number(item.days_of_cover) <= 14, 'text-amber-600': Number(item.days_of_cover) > 14 && Number(item.days_of_cover) <= 30 }">
                                {{ Number(item.days_of_cover) === 0 ? trans("we run out now") : `${trans("we run out in")} ~${Math.round(Number(item.days_of_cover))} ${trans("days")}` }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="inline-grid grid-cols-[3.5rem_1rem_3.5rem_1rem_3.5rem] items-center tabular-nums whitespace-nowrap">
                <span class="text-right text-gray-400">{{ useLocaleStore().number(Math.floor(Number(item.buyer_available ?? 0))) }}</span>
                <span class="text-center text-gray-300">+</span>
                <span class="text-right font-medium text-gray-700">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
                <span class="text-center text-gray-300">&rArr;</span>
                <span class="text-right text-gray-400">{{ useLocaleStore().number(Math.floor(Number(item.buyer_available ?? 0) + Number(item.quantity))) }}</span>
            </span>
        </template>
        <template #cell(amount)="{ item }">
            <span class="tabular-nums">
                {{ item.price_per_sko ? useLocaleStore().currencyFormat(orgPartner.currency, amountOf(item)) : "-" }}
            </span>
        </template>
        <template #cell(priority)="{ item }">
            <select
                v-if="item.state === 'open'"
                :value="item.priority"
                class="rounded border-gray-300 py-0.5 pl-2 pr-7 text-xs"
                :class="{ 'text-red-600': item.priority === 'urgent', 'text-amber-600': item.priority === 'high', 'text-gray-400': item.priority === 'low' }"
                @change="updateItem(item, { priority: ($event.target as HTMLSelectElement).value })"
            >
                <option v-for="priority in priorities" :key="priority" :value="priority">{{ trans(priority) }}</option>
            </select>
            <span v-else>{{ trans(item.priority) }}</span>
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}
            <span v-if="item.added_by_name" class="text-gray-400">· {{ item.added_by_name }}</span>
        </template>
        <template #cell(actions)="{ item }">
            <Button
                v-if="item.state === 'open'"
                icon="fal fa-trash-alt"
                :tooltip="trans('Remove from the shopping list')"
                type="negative"
                size="xs"
                @click="deleteItem(item)"
            />
        </template>
    </Table>
</template>
