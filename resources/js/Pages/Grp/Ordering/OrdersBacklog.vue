<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:45:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt } from '@fal'
import { ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import Table from '@/Components/Table/Table.vue'
import Tag from '@/Components/Tag.vue'
import Icon from '@/Components/Icon.vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    in_basket: {}
    submitted_paid: {}
    submitted_unpaid: {}
    picking: {}
    blocked: {}
    packed: {}
    packed_done: {}
    dispatched_today: {}
}>()



library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt)


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    <Table :resource="props[currentTab]" :name="currentTab">
        <template #cell(customer_name)="{ item }">
            <Link :href="route('grp.org.shops.show.crm.customers.show', {
                organisation: route().params.organisation,
                shop: route().params.shop,
                customer: item.customer_slug
            })" class="primaryLink">
                {{ item.customer_name }}
            </Link>
        </template>

        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <template #cell(payment_status)="{ item }">
            <Tag v-if="item.payment_state === 'completed'" :label="item.payment_status" theme="3" noHoverColor></Tag>
            <div v-else>
                {{ item.payment_status }}
            </div>
        </template>
    </Table>

</template>
