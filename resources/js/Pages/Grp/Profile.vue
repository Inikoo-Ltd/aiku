<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import TabsScrollable from "@/Components/Navigation/TabsScrollable.vue"

import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { computed, defineAsyncComponent, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { Component } from 'vue'

import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'

import ProfileHistory from "@/Components/Profile/ProfileHistory.vue"
import ProfileShowcase from "@/Components/Profile/ProfileShowcase.vue"
import ProfileHeader from "@/Components/Profile/ProfileHeader.vue"
import ProfileKPIs from "@/Components/Profile/ProfileKPIs.vue"
import ProfileTimesheets from "@/Components/Profile/ProfileTimesheets.vue"
import ProfileVisitLogs from "@/Components/Profile/ProfileVisitLogs.vue"
import ProfileTodo from "@/Components/Profile/ProfileTodo.vue"
import ProfileNotifications from "@/Components/Profile/ProfileNotifications.vue"
import ProfileApiTokens from "@/Components/Profile/ProfileApiTokens.vue"

import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { layoutStructure } from '@/Composables/useLayoutStructure'


import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faIdCard, faClipboardListCheck, faRabbitFast, faSlidersV, faRobot } from '@fal'
import { faInfoCircle } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLogoutAuth } from '@/Composables/useAppMethod'
library.add(faIdCard, faClipboardListCheck, faRabbitFast, faSlidersV, faRobot, faSpinnerThird, faInfoCircle)



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
        api_tokens: ProfileApiTokens,
        visit_logs: ProfileVisitLogs,
        timesheets: ProfileTimesheets,
        dashboard: ProfileShowcase,
        history: ProfileHistory,
    }

    return components[currentTab.value]
})
const handleTabUpdate = (newTabSlug: string) => {
    if (newTabSlug === currentTab.value) {
        return
    }

    const tab = dataProfile.value?.tabs?.navigation[newTabSlug]
    if (tab?.route?.name) {
        router.visit(route(tab.route.name, tab.route.parameters))
        return
    }

    fetchTabData(newTabSlug)
}
const isTabLoading = ref(false)
const headerLayoutVersion = ref(0)
const viewportFittedTabs = ['notifications', 'dashboard', 'timesheets']
const isViewportFittedTab = computed(() => viewportFittedTabs.includes(currentTab.value))
const dataTab = ref(null)
const fetchTabData = async (tabSlug: string) => {
    if (tabSlug === 'dashboard') {
        dataTab.value = {}
        currentTab.value = tabSlug
        return
    }

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
        case 'api_tokens':
            routeName = 'grp.profile.api-tokens.index'
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

const _tabContent = ref<HTMLElement | null>(null)
const tabContentHeight = ref<string>('auto')

const fitTabContentToViewport = () => {
    if (!_tabContent.value) {
        return
    }

    const stackedPanelBottomPadding = 24
    const available = window.innerHeight - _tabContent.value.getBoundingClientRect().top - stackedPanelBottomPadding
    tabContentHeight.value = `${Math.max(320, available)}px`
}

watch([currentTab, isTabLoading, headerLayoutVersion], async () => {
    await nextTick()
    fitTabContentToViewport()
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', fitTabContentToViewport)
})

onMounted(async () => {
    window.addEventListener('resize', fitTabContentToViewport)
    await fetchPageHead()
    currentTab.value = props?.data?.currentTab || currentTab.value
    await fetchTabData(currentTab.value)
    await nextTick()
    fitTabContentToViewport()
})

</script>


<template>
    <Head :title="trans('Profile')" />
    <ProfileHeader :isLoadingLogout="isLoadingLogout" @logout="onLogoutAuth" @loaded="headerLayoutVersion++" />

    <template v-if="dataProfile?.tabs?.navigation">
        <TabsScrollable :current="currentTab" :navigation="dataProfile?.tabs?.navigation"
            @update:tab="(tabSlug: string) => handleTabUpdate(tabSlug)" />

        <!-- Loading: main content -->
        <div v-if="isTabLoading" class="pt-32 w-full flex justify-center">
            <LoadingIcon size="2x" />
        </div>
        <div v-else-if="dataTab" ref="_tabContent" :style="{ height: tabContentHeight }"
            :class="isViewportFittedTab ? 'overflow-hidden' : 'pb-16 overflow-y-auto'">
            <component :is="component" :data="dataTab" :tab="currentTab" v-bind="isViewportFittedTab ? { layoutVersion: headerLayoutVersion } : {}" />
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
