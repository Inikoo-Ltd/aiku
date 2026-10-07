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
import { ctrans } from '@/Composables/useTrans'
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
        label: ctrans('Contact name'),
        key: 'contact',
        value: props.data.contact_name || '-'
    },
    {
        label: ctrans('Username'),
        key: 'username',
        value: props.data.username
    },
    {
        label: ctrans('Email'),
        key: 'email',
        value: props.data.email
    },
    {
        label: ctrans('Admin'),
        key: 'is_root',
        value: props.data.is_root
    },
    {
        label: ctrans('Status'),
        key: 'status',
        value: props.data.status
    },
    {
        label: ctrans('Auth type'),
        key: 'auth_type',
        value: props.data.auth_type
    },
    {
        label: ctrans('Created at'),
        key: 'created_At',
        value: useFormatTime(props.data.created_at)
    },
    {
        label: ctrans('Last active'),
        key: 'last_active_at',
        value: props.data.last_active_at ? useFormatTime(props.data.last_active_at) : ctrans('never')
    },
    {
        label: ctrans('Logins'),
        key: 'number_logins',
        value: props.data.number_logins
    },
    {
        label: ctrans('Last login'),
        key: 'last_login_at',
        value: props.data.last_login_at ? useFormatTime(props.data.last_login_at) : ctrans('never')
    },
    {
        label: ctrans('Last login IP'),
        key: 'last_login_ip',
        value: props.data.last_login_ip || '-'
    },
    {
        label: ctrans('Last login device'),
        key: 'last_device',
        value: [props.data.last_device, props.data.last_os].filter(Boolean).join(' · ') || '-'
    },
    {
        label: ctrans('Last login location'),
        key: 'last_location',
        value: [props.data.last_location?.city, props.data.last_location?.country].filter(Boolean).join(', ') || '-'
    },
    {
        label: ctrans('Failed logins'),
        key: 'number_failed_logins',
        value: props.data.number_failed_logins
    },
    {
        label: ctrans('Last failed login'),
        key: 'last_failed_login_at',
        value: props.data.last_failed_login_at ? useFormatTime(props.data.last_failed_login_at) : ctrans('never')
    },
    {
        label: ctrans('Last failed login IP'),
        key: 'last_failed_login_ip',
        value: props.data.last_failed_login_ip || '-'
    },
]

const fieldsByKey = Object.fromEntries(dataWebUser.map(field => [field.key, field]))
const pick = (keys: string[], wideKeys: string[] = []) =>
    keys.map(key => ({ ...fieldsByKey[key], wide: wideKeys.includes(key) }))

const webUserSections = [
    { title: ctrans('Account'), fields: pick(['contact', 'username', 'email', 'is_root', 'status', 'auth_type', 'created_At'], ['contact', 'username', 'email']) },
    { title: ctrans('Logins'), fields: pick(['last_active_at', 'number_logins', 'last_login_at', 'last_login_ip', 'last_device', 'last_location'], ['last_device', 'last_location']) },
    { title: ctrans('Failed logins'), fields: pick(['number_failed_logins', 'last_failed_login_at', 'last_failed_login_ip'], ['last_failed_login_ip']) },
]
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <template #otherBefore>
            <ModalConfirmationDelete
                v-if="canDelete"
                :routeDelete="props.data.delete_route"
                :title="ctrans('Delete this login?')"
                :description="ctrans('The customer will no longer be able to log in with it. This can not be undone. Their orders are not affected.')"
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

    <div v-if="currentTab === 'showcase'" class="grid grid-cols-1 gap-4 px-4 py-4 lg:grid-cols-3">
        <section v-for="group in webUserSections" :key="group.title" class="h-fit rounded-lg border border-gray-200 bg-white">
            <h3 class="border-b border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-800">{{ group.title }}</h3>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 px-4 py-3">
                <div v-for="field in group.fields" :key="field.key" class="min-w-0" :class="field.wide && 'col-span-2'">
                    <dt class="text-xs text-gray-400">{{ field.label }}</dt>
                    <dd class="mt-0.5 truncate text-sm font-medium text-gray-800" :title="String(field.value ?? '')">
                        <Tag v-if="field.key === 'status'" :theme="field.value ? 3 : undefined" :label="field.value ? ctrans('Active') : ctrans('Inactive')" />
                        <Tag v-else-if="field.key === 'is_root'" :theme="field.value ? 3 : undefined" :label="field.value ? ctrans('Yes') : ctrans('No')" />
                        <span v-else>{{ field.value }}</span>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
    <component
        v-else
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
    />
</template>
