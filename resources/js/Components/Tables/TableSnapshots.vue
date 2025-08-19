<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import Image from '@/Components/Image.vue'
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {faBroadcastTower, faSeedling, faGhost, faRecycle, faPoo} from '@fal'
import {useFormatTime} from "@/Composables/useFormatTime"
import {useLocaleStore} from '@/Stores/locale'
import { Link } from '@inertiajs/vue3'

const locale = useLocaleStore()


library.add(faSeedling, faGhost, faBroadcastTower, faRecycle, faPoo);
const props = defineProps<{
    data: object,
    tab?: string
}>()

function snapshotRoute(data: {}) {

    switch (route().current()) {
      case 'grp.org.shops.show.web.webpages.show':
        return route(
            'grp.org.shops.show.web.webpages.snapshot.show',
            [
                route().params['organisation'],
                route().params['shop'],
                route().params['website'],
                route().params['webpage'],
                data.id
            ]);
    }
}

</script>

<template>
    <Table :resource="data" class="mt-5" :name="tab">
        <!-- Icon -->
        <template #cell(state)="{ item: user }">
            <Icon :data="user.state" />
        </template>

        <!-- Publisher -->
        <template #cell(publisher)="{ item: user }">
            <div class="grid grid-cols-[min(25px)_minmax(90px,100%)] items-center">
                <div class="" :title="user.publisher">
                    <div class="h-5 aspect-square rounded-full overflow-hidden ring-1 ring-gray-200">
                        <Image :src="user.publisher_avatar" />
                    </div>
                </div>
                <div class="">{{ user['publisher'] }}</div>
            </div>
        </template>

        <!-- Date Published -->
        <template #cell(published_at)="{ item: user }">
            <div class="text-gray-500">
              <!--   <Link :href="snapshotRoute(user)" class="primaryLink"> -->
                  {{ useFormatTime(user['published_at'], { localeCode: locale.language.code, formatTime: 'hm' }) }}
              <!--   </Link> -->
            </div>
        </template>

        <!-- Published Until -->
        <template #cell(published_until)="{ item: user }">
            <div class="text-gray-500">{{ useFormatTime(user.published_until, { localeCode: locale.language.code, formatTime: 'hm' }) }}</div>
        </template>
    </Table>
</template>
