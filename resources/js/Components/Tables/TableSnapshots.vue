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
import { faBroadcastTower, faSeedling, faGhost, faRecycle, faPoo, faSignal } from '@fal'
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from '@/Stores/locale'
import { Link, router } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import axios from 'axios'
import { ref } from 'vue'
import Button from '../Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import ListItem from '@tiptap/extension-list-item'
import { trans } from 'laravel-vue-i18n'
const locale = useLocaleStore()


library.add(faSeedling, faGhost, faBroadcastTower, faRecycle, faPoo);
const props = defineProps<{
    data: object,
    tab?: string
    display_apply_button?: boolean
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
const loadingLive = ref<number[]>([])
const loadingUnpublished = ref<number[]>([])
const recycleLive = async (id) => {
    try {
        loadingLive.value.push(id)

        await axios.patch(
            route('grp.models.website.set-snapshot-as-live', {
                snapshot: id,
            })
        )

        notify({
            title: 'Success',
            text: 'Successfully set live version.',
            type: 'success',
        })
    } catch (error) {
        notify({
            title: 'Error',
            text:
                error?.response?.data?.message ||
                Object.values(error?.response?.data?.errors || {})?.[0] ||
                'Failed to set live snapshot.',
            type: 'error',
        })
    } finally {
        loadingLive.value = loadingLive.value.filter(i => i !== id)
    }
}

const recycleUnpublished = async (id) => {
    try {
        loadingUnpublished.value.push(id)

        await axios.patch(
            route('grp.models.website.set-snapshot-as-unpublished', {
                snapshot: id,
            })
        )

        notify({
            title: 'Success',
            text: 'Snapshot unpublished successfully.',
            type: 'success',
        })
    } catch (error) {
        notify({
            title: 'Error',
            text:
                error?.response?.data?.message ||
                Object.values(error?.response?.data?.errors || {})?.[0] ||
                'Failed to unpublish snapshot.',
            type: 'error',
        })
    } finally {
        loadingUnpublished.value = loadingUnpublished.value.filter(i => i !== id)
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
                <Link :href="snapshotRoute(user)" class="primaryLink">
                    {{ useFormatTime(user['published_at'], { localeCode: locale.language.code, formatTime: 'hm' }) }}
                </Link>
                <FontAwesomeIcon v-if="display_apply_button" :icon="faSignal" class="ml-2 text-green-500 cursor-pointer"
                    @click="doThis(user.id)" />
            </div>
        </template>

        <!-- Published Until -->
        <template #cell(published_until)="{ item: user }">
            <div class="text-gray-500">{{ useFormatTime(user.published_until, {
                localeCode: locale.language.code,
                formatTime: 'hm'
            }) }}</div>
        </template>

        <template #cell(recyclable)="{ item }">
            <slot name="banner-snapshot" :item></slot>
        </template>

        <template #cell(action)="{ item }">
            <div class="flex items-center gap-2">
                <Button :type="'positive'" :icon="faRecycle"
                    v-tooltip="trans('Recycle the live version to this version')" @click="() => recycleLive(item.id)"
                    :loading="loadingLive.includes(item.id)" />

                <Button :type="'primary'" :icon="faRecycle"
                    v-tooltip="trans('Recycle the unpublished version to this version')"
                    @click="() => recycleUnpublished(item.id)" :loading="loadingUnpublished.includes(item.id)" />
            </div>
        </template>
    </Table>
</template>
