<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 17 May 2024 13:09:02 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt } from "@far"
import { faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote } from "@fal"
import Tag from "@/Components/Tag.vue"
import type { Meta, Links } from "@/types/Table"
import { Pallet } from "@/types/Pallet"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"

library.add(faTrashAlt, faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp,
    faStickyNote
)


defineProps<{
    data: {
        data: {}[]
        links: Links
        meta: Meta
    },
    tab?: string
}>()


function palletRoute(pallet: Pallet) {
    switch (route().current()) {
        case "grp.overview.fulfilment.pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    pallet.organisation_slug,
                    pallet.fulfilment_slug,
                    pallet.slug
                ])
        case "grp.org.fulfilments.show.operations.pallets.current.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.operations.returned_pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.returned_pallets.index",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.crm.customers.show":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.pallets.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    route().params["fulfilmentCustomer"],
                    pallet.slug
                ])
        case "grp.org.fulfilments.show.crm.customers.show.pallets.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.pallets.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    route().params["fulfilmentCustomer"],
                    pallet.slug
                ])

        default:
            return []
    }
}

function fulfilmentCustomerRoute(pallet: Pallet) {
    switch (route().current()) {
        case "grp.overview.fulfilment.pallets.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show",
                [
                    pallet.organisation_slug,
                    pallet.fulfilment_slug,
                    pallet.fulfilment_customer_slug
                ])
        case "grp.org.fulfilments.show.operations.pallets.current.index":
        case "grp.org.fulfilments.show.operations.returned_pallets.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    pallet.fulfilment_customer_slug
                ])

        default:
            return []
    }
}

async function copyStoredItemValue(item: { name?: string; reference?: string; quantity?: number | string }) {
    const quantity = Number(item.quantity ?? 0)
    const copiedValue = `${item.name || item.reference || "-"} - ${quantity}`

    try {
        await navigator.clipboard.writeText(copiedValue)
        notify({
            title: "Copied",
            text: `Copied item SKU value: ${copiedValue}`,
            type: "success",
        })
    } catch {
        notify({
            title: "Copy failed",
            text: "Unable to copy item SKU value",
            type: "error",
        })
    }
}

// const palletSelected = ref<{ [key: string]: number } | null>({
//     abc: 1
// })  // Helper on which pallet selected to move
// const isLoading = ref(false)

// const showCheckboxes = false

</script>

<template>
    <!-- <pre>{{ props.data.data[0] }}</pre> -->
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: pallet }">
            <component :is="pallet.slug ? Link : 'div'" :href="pallet.slug ? palletRoute(pallet) : undefined"
                :class="pallet.slug ? 'primaryLink' : ''">
                {{ pallet.reference }}
            </component>
        </template>


        <!-- Column: Customer -->
        <template #cell(fulfilment_customer_name)="{ item: pallet }">
            <Link :href="fulfilmentCustomerRoute(pallet)" class="secondaryLink">
                {{ pallet["fulfilment_customer_name"] }}
            </Link>
        </template>


        <!-- Column: Customer Reference -->
        <template #cell(customer_reference)="{ item: item }">
            <div>
                {{ item.customer_reference }}
                <span v-if="item.notes" class="text-gray-400 text-xs ml-1">
                    <FontAwesomeIcon icon="fal fa-sticky-note" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ item.notes }}
                </span>
            </div>
        </template>


        <template #cell(state)="{ item: pallet }">
            <Icon :data="pallet['state_icon']" />
        </template>


        <!-- Column: Notes -->
        <template #cell(notes)="{ item: pallet }">
            <div class="text-gray-500 italic">{{ pallet.notes }}</div>
        </template>


        <!-- Column: Stored Items -->
        <template #cell(stored_items)="{ item: pallet }">
            <div v-if="pallet.stored_items.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag v-for="item of pallet.stored_items" :theme="item.id"
                    :label="`${item.reference} (${item.quantity})`" :closeButton="false" :stringToColor="true">
                    <template #label>
                        <div class="whitespace-nowrap text-xs cursor-pointer" @click="copyStoredItemValue(item)">
                            {{ item["reference"] }} (<span class="font-light">{{ item["quantity"] }}</span>)
                        </div>
                    </template>
                </Tag>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                No items in this pallet
            </div>
        </template>

        <!-- Column: Action (move pallet) -->
        <template #cell(actions)="{ item }">

        </template>

        <!-- Column: Icon (type) -->
        <template #cell(type_icon)="{ item: pallet }">
            <Icon :data="pallet.type_icon" class="px-1" />
        </template>


    </Table>
</template>

<style src="../../../../../../../node_modules/@vueform/multiselect/themes/default.css"></style>
