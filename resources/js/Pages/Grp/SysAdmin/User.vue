<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 14 Sept 2022 02:15:21 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, defineAsyncComponent, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import {faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faShieldCheck, faUserTag} from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { capitalize } from "@/Composables/capitalize"
import { faRoad } from "@fas"
import SysadminUserShowcase from '@/Components/Showcases/Grp/SysadminUserShowcase.vue'
import UserPermissions from '@/Components/Sysadmin/UserPermissions.vue'
import UserRoles from '@/Components/Sysadmin/UserRoles.vue'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import type { Component } from 'vue'
library.add(faIdCard, faUser, faClock, faDatabase, faEnvelope, faHexagon, faFile, faRoad, faShieldCheck, faUserTag)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {}
    request_logs?: {}
    history?: {}
    permissions?: {}
    roles?: {}
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: SysadminUserShowcase,
        details: ModelDetails,
        request_logs: TableUserRequestLogs,
        history: TableHistories,
        permissions: UserPermissions,
        roles: UserRoles,
    }
    return components[currentTab.value]

})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"></component>
</template>
