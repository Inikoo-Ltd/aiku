<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { computed, defineAsyncComponent, inject, onMounted, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableTimesheets from "@/Components/Tables/Grp/Org/HumanResources/TableTimesheets.vue"
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue"
import ProfileShowcase from "@/Components/Profile/ProfileShowcase.vue"
// import TableNotifications from "@/Components/Profile/TableNotifications.vue"
import ProfileKPIs from "@/Components/Profile/ProfileKPIs.vue"
import ProfileTimesheets from "@/Components/Profile/ProfileTimesheets.vue"
import ProfileVisitLogs from "@/Components/Profile/ProfileVisitLogs.vue"
import ProfileTodo from "@/Components/Profile/ProfileTodo.vue"
import ProfileNotifications from "@/Components/Profile/ProfileNotifications.vue"
// import ProfileDashboard from "@/Components/Profile/ProfileDashboard.vue"
import EditProfile from "@/Pages/Grp/EditProfile.vue"
import SettingProfile from "@/Pages/Grp/SettingProfile.vue"

import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'


import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faIdCard, faClipboardListCheck, faRabbitFast, faSlidersV } from '@fal'
import { faInfoCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLogoutAuth } from '@/Composables/useAppMethod'
library.add(faIdCard, faClipboardListCheck, faRabbitFast, faSlidersV, faSpinnerThird, faInfoCircle)

/* const EditProfile = defineAsyncComponent(() => import("@/Pages/Grp/EditProfile.vue")) */


const props = defineProps<{
    data?: {
        currentTab?: string
    }

}>()


const layout = inject('layout', layoutStructure)


// Section: fetch PageHead and Tabs list
const dataProfile = ref<{ pageHead: PageHeadingTypes, tabs: TSTabs } | null>(null)
const fetchPageHead = async () => {
    try {
        const { data } = await axios.get(
            route('grp.profile.page-head-tabs.show')
        )
        dataProfile.value = data
        currentTab.value = data.tabs.current
        // console.log('response pageHead', data)
    } catch (error: any) {
        dataProfile.value = null
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to show Profile page.'),
            type: 'error',
        })
    }
}


// Section: Fetch Tab data
const currentTab = ref('')
const component = computed(() => {
    const components: Component = {
        todo: ProfileTodo,
        notifications: ProfileNotifications,
        kpi: ProfileKPIs,
        visit_logs: ProfileVisitLogs,
        timesheets: ProfileTimesheets,
        dashboard: ProfileShowcase,
        history: TableHistories,
    }

    return components[currentTab.value]
})
const handleTabUpdate = (newTabSlug: string) => {
    if (newTabSlug === currentTab.value) {
        return
    }

    fetchTabData(newTabSlug)
}
const isTabLoading = ref(false)
const dataTab = ref(null)
const fetchTabData = async (tabSlug: string) => {
    isTabLoading.value = true
    let routeName = ''

    switch (tabSlug) {
        case 'todo':
            routeName = 'grp.profile.todo.index'
            break
        case 'notifications':
            routeName = 'grp.profile.notifications.index'
            break
        case 'kpi':
            routeName = 'grp.profile.kpis.index'
            break
        case 'visit_logs':
            routeName = 'grp.profile.visit-logs.index'
            break
        case 'timesheets':
            routeName = 'grp.profile.timesheets.index'
            break
        case 'dashboard':
            routeName = 'grp.profile.showcase.show'
            break
        case 'history':
            routeName = 'grp.profile.history.index'
            break
    }

    try {
        console.log('tab', tabSlug, route(routeName))
        const { data } = await axios.get(
            route(routeName), {
                headers: {
                    'Content-Type': 'application/json'
                }
            }
        )
        dataTab.value = data
        console.log('daaataaa', dataTab.value)
        currentTab.value = tabSlug
        // console.log('response', dataTab.value)
    } catch (error: any) {
        dataTab.value = null
        notify({
            title: trans('Something went wrong.'),
            text: `Failed to show ${dataProfile.value?.tabs.navigation[tabSlug].title} tab.`,
            type: 'error',
        })
    } finally {
        isTabLoading.value = false

    }

}

// Section: LogoutRetina
const isLoadingLogout = ref(false)
const onLogoutAuth = () => {
    useLogoutAuth(layout.user, {
        onStart: () => isLoadingLogout.value = true,
        onError: () => isLoadingLogout.value = false,
        onSuccess: () => layout.stackedComponents = []
    })
}

onMounted(async () => {
    await fetchPageHead()
    currentTab.value = props?.data?.currentTab || currentTab.value
    await fetchTabData(currentTab.value)
})

</script>


<template>
    <Head :title="trans('Profile')" />
    <PageHeading v-if="dataProfile?.pageHead" :data="dataProfile?.pageHead">
        <template #button-edit-profile="{ action }">
            <Button
                @click="() => layout.stackedComponents.push({ component: EditProfile })"
                :label="action.label"
                type="edit"
            />
            <Button
                @click="() => layout.stackedComponents.push({ component: SettingProfile })"
                :label="trans('Preferences')"
                icon="fal fa-sliders-v"
                type="edit"
                class="-ml-2"
            />

            <Button
                @click="() => onLogoutAuth()"
                label="Logout"
                :loading="isLoadingLogout"
                icon="fal fa-sign-out-alt"
                :style="'negative'"
            />
        </template>
    </PageHeading>

    <template v-if="dataProfile?.tabs?.navigation">
        <Tabs :current="currentTab" :navigation="dataProfile?.tabs?.navigation"
            @update:tab="(tabSlug: string) => handleTabUpdate(tabSlug)" />

        <!-- Loading: main content -->
        <div v-if="isTabLoading" class="pt-32 w-full flex justify-center">
            <LoadingIcon size="2x" />
        </div>
        <div v-else-if="dataTab" class="pb-16 h-full overflow-auto">
            <component :is="component" :data="dataTab" :tab="currentTab" />
        </div>
        <div v-else class="h-full w-full flex items-center justify-center text-gray-400 italic">
            {{ trans('No data to shown.') }}
        </div>
    </template>

    <!-- Loading: Navigation -->
    <div v-else class="pt-8 w-full flex items-center justify-center">
        <LoadingIcon size="2x" />
    </div>
</template>
