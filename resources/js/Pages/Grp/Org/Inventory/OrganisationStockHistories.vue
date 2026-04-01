<!--
 -  Author: stewicca <stewicalf@gmail.com>
 -  Created: Tue, 01 Apr 2026
 -  Copyright (c) 2026, Inikoo LTD
 -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrganisationStockHistories from "@/Components/Tables/Grp/Org/Inventory/TableOrganisationStockHistories.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarDay, faCalendarWeek, faCalendarAlt, faCalendar, faDownload } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

library.add(faCalendarDay, faCalendarWeek, faCalendarAlt, faCalendar, faDownload)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    download_route: routeType
    daily?: {}
    weekly?: {}
    monthly?: {}
    yearly?: {}
}>()

const currentTab = ref<string>(props?.tabs?.current ?? "daily")
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

function exportUrl(): string {
    if (!props.download_route?.name) return ""
    return route(props.download_route.name, {
        ...props.download_route.parameters,
        tab: currentTab.value,
        type: 'xlsx'
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <a :href="exportUrl()" download target="_blank" rel="noopener">
                <Button :icon="faDownload" label="Excel" type="tertiary" />
            </a>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <TableOrganisationStockHistories
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
    />
</template>
