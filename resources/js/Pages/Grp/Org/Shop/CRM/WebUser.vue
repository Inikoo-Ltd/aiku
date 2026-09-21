<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Nov 2022 10:39:51 Central Indonesia Time, Ubud, Bali, Indonesia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faGlobe, faTrashAlt } from '@fal'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from '@/types/PageHeading'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import Tag from '@/Components/Tag.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableWebUserLogins from "@/Components/Tables/Grp/Org/CRM/TableWebUserLogins.vue"
import TableWebUserFailedLogins from "@/Components/Tables/Grp/Org/CRM/TableWebUserFailedLogins.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";


library.add(faGlobe, faTrashAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
    canDelete?: boolean
    tabs: {
        current: string
        navigation: {}
    }
    logins?: {}
    failed_logins?: {}
    history?: {}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        logins: TableWebUserLogins,
        failed_logins: TableWebUserFailedLogins,
        history: TableHistories,
    }

    return components[currentTab.value]
})

const dataWebUser = [
    {
        label: trans('Contact name'),
        key: 'contact',
        value: props.data.contact_name || '-'
    },
    {
        label: trans('Username'),
        key: 'username',
        value: props.data.username
    },
    {
        label: trans('Email'),
        key: 'email',
        value: props.data.email
    },
    {
        label: trans('Admin'),
        key: 'is_root',
        value: props.data.is_root
    },
    {
        label: trans('Status'),
        key: 'status',
        value: props.data.status
    },
    {
        label: trans('Auth type'),
        key: 'auth_type',
        value: props.data.auth_type
    },
    {
        label: trans('Created at'),
        key: 'created_At',
        value: useFormatTime(props.data.created_at)
    },
    {
        label: trans('Last active'),
        key: 'last_active_at',
        value: props.data.last_active_at ? useFormatTime(props.data.last_active_at) : trans('never')
    },
    {
        label: trans('Logins'),
        key: 'number_logins',
        value: props.data.number_logins
    },
    {
        label: trans('Last login'),
        key: 'last_login_at',
        value: props.data.last_login_at ? useFormatTime(props.data.last_login_at) : trans('never')
    },
    {
        label: trans('Last login IP'),
        key: 'last_login_ip',
        value: props.data.last_login_ip || '-'
    },
    {
        label: trans('Last login device'),
        key: 'last_device',
        value: [props.data.last_device, props.data.last_os].filter(Boolean).join(' · ') || '-'
    },
    {
        label: trans('Last login location'),
        key: 'last_location',
        value: [props.data.last_location?.city, props.data.last_location?.country].filter(Boolean).join(', ') || '-'
    },
    {
        label: trans('Failed logins'),
        key: 'number_failed_logins',
        value: props.data.number_failed_logins
    },
    {
        label: trans('Last failed login'),
        key: 'last_failed_login_at',
        value: props.data.last_failed_login_at ? useFormatTime(props.data.last_failed_login_at) : trans('never')
    },
    {
        label: trans('Last failed login IP'),
        key: 'last_failed_login_ip',
        value: props.data.last_failed_login_ip || '-'
    },
]
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <template #otherBefore>
            <ModalConfirmationDelete
                v-if="canDelete"
                :routeDelete="props.data.delete_route"
                :title="trans('Delete this login?')"
                :description="trans('The customer will no longer be able to log in with it. This can not be undone. Their orders are not affected.')"
                isFullLoading
            >
                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        icon="fal fa-trash-alt"
                        type="negative"
                        @click="changeModel"
                    />
                </template>
            </ModalConfirmationDelete>

        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'showcase'" class="grid grid-cols-2 py-4 px-6">

        <!-- Section: field data -->
        <div>
            <div class="text-xl font-bold mb-2">{{ trans('Web user details') }}</div>
            <div class="h-fit w-80 relative grid grid-cols-1 divide-y divide-gray-300 border border-gray-300 rounded-md">
                <div v-for="(print, index) in dataWebUser" :key="index" class="py-2.5 px-4">
                    <div class="text-gray-400 text-xs">
                        {{ print.label }}
                    </div>
                    <div class="font-medium text-sm">
                        <Tag v-if="print.key === 'status'" :theme="print.value ? 3 : undefined" :label="print.value ? 'Active' : 'Inactive'" />
                        <Tag v-else-if="print.key === 'is_root'" :theme="print.value ? 3 : undefined" :label="print.value ? trans('Yes') : trans('No')" />
                        <span v-else>{{print.value}}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <component
        v-else
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
    />
</template>
