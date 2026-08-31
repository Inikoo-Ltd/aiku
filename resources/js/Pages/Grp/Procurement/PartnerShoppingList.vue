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
        <template #cell(image)="{ item }">
            <div class="w-12 h-12 rounded">
                <Image :src="item.image_sources" />
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="inline-grid grid-cols-[3.5rem_1rem_3.5rem_1rem_3.5rem] items-center tabular-nums whitespace-nowrap">
                <span class="text-right text-gray-400">{{ useLocaleStore().number(Number(item.buyer_available ?? 0)) }}</span>
                <span class="text-center text-gray-300">+</span>
                <span class="text-right font-medium text-gray-700">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
                <span class="text-center text-gray-300">&rArr;</span>
                <span class="text-right text-gray-400">{{ useLocaleStore().number(Number(item.buyer_available ?? 0) + Number(item.quantity)) }}</span>
            </span>
        </template>
        <template #cell(amount)="{ item }">
            <span class="tabular-nums">
                {{ item.price_per_sko ? useLocaleStore().currencyFormat(orgPartner.currency, amountOf(item)) : "-" }}
            </span>
        </template>
        <template #cell(needed_by)="{ item }">
            {{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}
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
                type="delete"
                size="xs"
                @click="deleteItem(item)"
            />
        </template>
    </Table>
</template>
