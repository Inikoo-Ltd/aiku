<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import qs from "qs"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableStockDeliveries from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveries.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ReceiveSupplierParcelDialog from "@/Components/Procurement/ReceiveSupplierParcelDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruckContainer, faBoxCheck } from "@fal"
library.add(faTruckContainer)

type FilterOption = { label: string; value: string; count: number; tooltip?: string }
type RouteDef = { name: string; parameters: (string | number)[] }

defineProps<{
    pageHead: object
    title: string
    data: object
    filterOptions?: { organisation?: FilterOption[] }
    receiveSupplierParcel?: { options: RouteDef; receive: RouteDef } | null
}>()

const isReceivingParcel = ref(false)

const currentQuery = () => qs.parse(location.search.substring(1)) as Record<string, any>
const organisation = ref<string | null>(currentQuery().filter?.organisation ?? null)

watch(organisation, () => {
    const query = currentQuery()
    query.filter = { ...(query.filter ?? {}), organisation: organisation.value || undefined }
    delete query.page
    router.get(`${location.pathname}?${qs.stringify(query, { skipNulls: true })}`, {}, { preserveState: true, preserveScroll: true, replace: true })
})
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
        <template v-if="receiveSupplierParcel" #otherBefore>
            <Button type="tertiary" :label="ctrans('Receive supplier parcel')" @click="isReceivingParcel = true">
                <template #icon>
                    <FontAwesomeIcon :icon="faBoxCheck" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </PageHeading>
    <ReceiveSupplierParcelDialog v-if="receiveSupplierParcel" v-model:visible="isReceivingParcel" :routes="receiveSupplierParcel" />
    <div v-if="filterOptions?.organisation?.length" class="flex flex-wrap gap-x-4 gap-y-2 px-4 pt-3">
        <PillFilterBar :label="ctrans('Organisation')" :options="filterOptions.organisation" :selected="organisation" @select="(value) => (organisation = value)" />
    </div>
    <TableStockDeliveries :data="data" />
</template>
