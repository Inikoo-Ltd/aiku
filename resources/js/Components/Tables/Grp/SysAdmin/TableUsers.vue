<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { User } from "@/types/user"
import { trans } from "laravel-vue-i18n"
import Image from "@/Components/Image.vue"
import Icon from '@/Components/Icon.vue'

import { faCheck, faTimes, faUserCircle, faYinYang, faKey } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faUserCircle, faTimes, faCheck, faYinYang, faKey)

const props = defineProps<{
    data: {}
    tab?: string
}>()


function userRoute(user: User) {
    switch (route().current()) {
        case "grp.sysadmin.users.index":
            return route( "grp.sysadmin.users.show", [user.username])
        default:
            return null
    }
}


</script>

<template>
    <!-- <pre>{{ data }}</pre>
    {{ tab }} -->

    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Status -->
        <template #cell(status)="{ item: user }">
            <Icon :data="user.status" class="px-1" />
        </template>

        <!-- Column: Username -->
        <template #cell(username)="{ item: user }">
            <Link v-if="userRoute(user)" :href="userRoute(user)" class="primaryLink">
                <template v-if="user['username']">{{ user["username"] }}</template>
                <span v-else class="italic">{{ trans("Not set") }}</span>
            </Link>
            <div v-else>
                <template v-if="user['username']">{{ user["username"] }}</template>
                <span v-else class="italic">{{ trans("Not set") }}</span>
            </div>

            <div v-if="user.total_api_tokens > 0" v-tooltip="user.total_expired_api_tokens + ' ' + trans('keys was expired')" class="inline w-fit">
                <FontAwesomeIcon icon="fal fa-key" class="text-gray-400" fixed-width aria-hidden="true" />
                <span class="text-orange-500">{{ user.total_api_tokens}}</span>
            </div>
        </template>

        <!-- Column: Image -->
        <template #cell(image)="{ item: user }">

            <div class="flex justify-center">
                <Image :src="user['image']" class="w-6 aspect-square rounded-full overflow-hidden shadow"
                    :alt="user.username" />
            </div>
        </template>

        <!-- <template #cell(name)="{ item: user }">
            <div class="asdzxc">
                {{ user["parent"]["name"] }}
            </div>
        </template> -->

        <template #cell(parent_type)="{ item: user }">
            <Link v-if="user['parent_type'] === 'Employee'" :href="route(
                'grp.org.hr.employees.show',
                [
                    user['parent']['organisation_slug'],
                    user['parent']['slug']]
                )" class="secondaryLink">
                {{ trans("Employee") }}
            </Link>

            <Link v-else-if="user['parent_type'] === 'Guest'"
                :href="route('grp.sysadmin.guests.show', user['parent']['slug'])" class="secondaryLink">
                {{ trans("Guest") }}
            </Link>
        </template>

    </Table>
</template>
