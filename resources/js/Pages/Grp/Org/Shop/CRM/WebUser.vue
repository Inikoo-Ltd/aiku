<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Nov 2022 10:39:51 Central Indonesia Time, Ubud, Bali, Indonesia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { route } from 'ziggy-js'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGlobe, faTrashAlt, faExclamationTriangle } from '@fal'
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from '@/types/PageHeading'
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'
import Tag from '@/Components/Tag.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import TableWebUserLogins from "@/Components/Tables/Grp/Org/CRM/TableWebUserLogins.vue"
import TableWebUserFailedLogins from "@/Components/Tables/Grp/Org/CRM/TableWebUserFailedLogins.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";


library.add(faGlobe, faTrashAlt, faExclamationTriangle)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: Record<string, any>
    visits: {
        retention_days: number
        number_visits: number
        number_page_views: number
        visits_url: string | null
        last_visit: {
            first_seen_at: string
            page_views: number
            device: string
            landing_page: string | null
            source: string | null
            page_views_url: string | null
        } | null
    }
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

const formatDate = (date?: string | null) => date ? useFormatTime(date, { formatTime: 'hm' }) : null

const lastAttemptFailed = computed(() => {
    if (!props.data.last_failed_login_at) {
        return false
    }

    return !props.data.last_login_at || new Date(props.data.last_failed_login_at) > new Date(props.data.last_login_at)
})

const lastSignInDevice = computed(() => [props.data.last_device, props.data.last_os].filter(Boolean).join(' · '))

const authTypeLabel = computed(() => props.data.auth_type === 'aurora' ? ctrans('Aurora password (legacy)') : ctrans('Aiku password'))

const customerHref = computed(() => {
    const routeParams = route().params as Record<string, string>

    return routeParams.shop && props.data.customer?.slug
        ? route('grp.org.shops.show.crm.customers.show', [routeParams.organisation, routeParams.shop, props.data.customer.slug])
        : null
})
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

    <div v-if="currentTab === 'showcase'" class="grid gap-x-12 gap-y-10 px-6 py-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        <section>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-gray-900">{{ data.contact_name || data.username }}</h2>
                <Tag :theme="data.status ? 3 : undefined" :label="data.status ? ctrans('Active') : ctrans('Inactive')" />
                <Tag v-if="data.is_root" :label="ctrans('Admin')" />
            </div>

            <dl class="mt-4 divide-y divide-gray-200 border-y border-gray-200 text-sm">
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5">
                    <dt class="text-gray-500">{{ ctrans('Username') }}</dt>
                    <dd class="break-all text-gray-900">{{ data.username }}</dd>
                </div>
                <div v-if="data.email && data.email !== data.username" class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5">
                    <dt class="text-gray-500">{{ ctrans('Email') }}</dt>
                    <dd class="break-all text-gray-900">{{ data.email }}</dd>
                </div>
                <div v-if="data.customer" class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5">
                    <dt class="text-gray-500">{{ ctrans('Customer') }}</dt>
                    <dd class="text-gray-900">
                        <Link v-if="customerHref" :href="customerHref" class="primaryLink">{{ data.customer.name }}</Link>
                        <span v-else>{{ data.customer.name }}</span>
                        <span class="ml-1.5 text-gray-500">{{ data.customer.reference }}</span>
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5">
                    <dt class="text-gray-500">{{ ctrans('Signs in with') }}</dt>
                    <dd class="text-gray-900">{{ authTypeLabel }}</dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3 py-2.5">
                    <dt class="text-gray-500">{{ ctrans('Created') }}</dt>
                    <dd class="text-gray-900">{{ formatDate(data.created_at) }}</dd>
                </div>
            </dl>
        </section>

        <div class="space-y-10">
            <section>
                <h3 class="text-sm font-semibold text-gray-900">{{ ctrans('Sign-in') }}</h3>

                <div v-if="lastAttemptFailed" class="mt-3 flex items-start gap-2 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
                    <span>
                        {{ ctrans('The last sign-in attempt failed, on :date', { date: formatDate(data.last_failed_login_at) }) }}<template v-if="data.last_failed_login_ip">, {{ ctrans('from :ip', { ip: data.last_failed_login_ip }) }}</template>.
                    </span>
                </div>

                <dl class="mt-4 grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-gray-500">{{ ctrans('Last active') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ formatDate(data.last_active_at) ?? ctrans('Never') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ ctrans('Last sign-in') }}</dt>
                        <dd class="mt-1 text-gray-900">{{ formatDate(data.last_login_at) ?? ctrans('Never') }}</dd>
                        <dd v-if="data.last_login_at" class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                            <span v-if="lastSignInDevice">{{ lastSignInDevice }}</span>
                            <AddressLocation v-if="data.last_location" :data="data.last_location" />
                            <span v-if="data.last_login_ip">{{ data.last_login_ip }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ ctrans('Sign-ins') }}</dt>
                        <dd class="mt-1">
                            <button v-if="data.number_logins" type="button" class="primaryLink tabular-nums" @click="handleTabUpdate('logins')">{{ data.number_logins }}</button>
                            <span v-else class="text-gray-900">0</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ ctrans('Failed attempts') }}</dt>
                        <dd class="mt-1">
                            <button v-if="data.number_failed_logins" type="button" class="primaryLink tabular-nums" @click="handleTabUpdate('failed_logins')">{{ data.number_failed_logins }}</button>
                            <span v-else class="text-gray-900">0</span>
                        </dd>
                        <dd v-if="data.number_failed_logins && !lastAttemptFailed" class="mt-1 text-xs text-gray-500">
                            {{ ctrans('Last on :date', { date: formatDate(data.last_failed_login_at) }) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section>
                <div class="flex items-baseline justify-between gap-4">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ ctrans('Website visits') }}
                        <span class="ml-1 font-normal text-gray-500">{{ ctrans('last :days days', { days: visits.retention_days }) }}</span>
                    </h3>
                    <Link v-if="visits.visits_url && visits.number_visits" :href="visits.visits_url" class="primaryLink text-sm">
                        {{ ctrans('See all visits') }}
                    </Link>
                </div>

                <p v-if="!visits.number_visits" class="mt-3 text-sm text-gray-500">
                    {{ ctrans('No visits while signed in to this account.') }}
                </p>

                <template v-else>
                    <p class="mt-3 text-sm text-gray-900">
                        {{ ctrans(':visits visits, :pages pages viewed', { visits: visits.number_visits, pages: visits.number_page_views }) }}
                    </p>

                    <dl v-if="visits.last_visit" class="mt-4 grid gap-x-8 gap-y-5 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <dt class="text-gray-500">{{ ctrans('Last visit') }}</dt>
                            <dd class="mt-1 text-gray-900">
                                <Link v-if="visits.last_visit.page_views_url" :href="visits.last_visit.page_views_url" class="primaryLink">
                                    {{ formatDate(visits.last_visit.first_seen_at) }}
                                </Link>
                                <span v-else>{{ formatDate(visits.last_visit.first_seen_at) }}</span>
                            </dd>
                            <dd class="mt-1 text-xs text-gray-500">
                                {{ ctrans(':pages pages on :device', { pages: visits.last_visit.page_views, device: visits.last_visit.device }) }}
                            </dd>
                        </div>
                        <div v-if="visits.last_visit.source">
                            <dt class="text-gray-500">{{ ctrans('Came from') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ visits.last_visit.source }}</dd>
                        </div>
                        <div v-if="visits.last_visit.landing_page" class="xl:col-span-2">
                            <dt class="text-gray-500">{{ ctrans('Landed on') }}</dt>
                            <dd class="mt-1 truncate text-gray-900" :title="visits.last_visit.landing_page">{{ visits.last_visit.landing_page }}</dd>
                        </div>
                    </dl>
                </template>
            </section>
        </div>
    </div>
    <component
        v-else
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
    />
</template>
