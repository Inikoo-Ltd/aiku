<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject } from "vue"
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faExclamationTriangle, faLock, faCircle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faCheckCircle, faExclamationTriangle, faLock, faCircle)

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const statusLabels: Record<string, string> = {
    ok: ctrans("Read fine last time"),
    login_failed: ctrans("Could not log in, check the email and password"),
    blocked: ctrans("The website blocked us"),
    error: ctrans("Could not open the website"),
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item }">
            <FontAwesomeIcon v-if="item.status === 'ok'" icon="fal fa-check-circle" class="text-green-500" v-tooltip="statusLabels.ok" fixed-width />
            <FontAwesomeIcon v-else-if="item.status" icon="fal fa-exclamation-triangle" class="text-red-500" v-tooltip="statusLabels[item.status] + (item.last_error ? ': ' + item.last_error : '')" fixed-width />
            <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-300" v-tooltip="ctrans('Not read yet')" fixed-width />
        </template>
        <template #cell(name)="{ item }">
            <Link :href="route('grp.masters.master_shops.show.competitors.edit', { masterShop: route().params['masterShop'], competitor: item.id })" class="primaryLink">
                {{ item.name }}
            </Link>
            <a :href="item.website" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs text-gray-400 hover:underline">{{ item.website }}</a>
            <span v-if="!item.has_search" class="ml-2 text-xs text-amber-600">{{ ctrans("No search link") }}</span>
        </template>
        <template #cell(has_login)="{ item }">
            <FontAwesomeIcon v-if="item.has_login" icon="fal fa-lock" class="text-gray-500" v-tooltip="ctrans('Logs in with our trade account')" fixed-width />
            <span v-else class="text-gray-300">-</span>
        </template>
        <template #cell(fetched_at)="{ item }">
            {{ item.fetched_at ? new Date(item.fetched_at).toLocaleDateString(locale.language.code) : "" }}
        </template>
    </Table>
</template>
