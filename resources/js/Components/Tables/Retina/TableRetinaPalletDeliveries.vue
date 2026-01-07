<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 06 Feb 2025 21:46:44 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fas"
import { faCheckDouble, faShare, faCross } from "@fal"
import Modal from "@/Components/Utils/Modal.vue"
import { ref } from 'vue'
import { PalletDelivery } from "@/types/pallet-delivery"
import Icon from "@/Components/Icon.vue"
import { useFormatTime, useDaysLeftFromToday } from '@/Composables/useFormatTime'
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale";

library.add(faPlus, faCheckDouble, faShare, faCross)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const openModal = ref(false)
const loading = ref(false)
const locale = useLocaleStore();

function palletDeliveryRoute(palletDelivery: PalletDelivery) {

  return route(
    'retina.fulfilment.storage.pallet_deliveries.show',
    [
      palletDelivery.slug
    ])


}




const onClickNewPalletDelivery = (action: Action) => {
    if (action.disabled) openModal.value = true
    else {
        const href = action.route?.name ? route(action.route?.name, action.route?.parameters) : action.route?.name ? route(action.route?.name, action.route?.parameters) : '#'
        const method = action.route?.method ?? 'get'
        router[method](
            href,
            {
                onBefore: () => { loading.value = true },
                onerror: () => { loading.value = false }
            })
    }
};


const isLoading = ref<string | boolean>(false)
const onClickReceived = (receivedRoute: routeType) => {
    router[receivedRoute.method || 'post'](
        route(receivedRoute.name, receivedRoute.parameters),
        {},
        {
            onStart: () => isLoading.value = 'received',
            onError: () => {
                notify({
                    title: trans('Something went wrong'),
                    text: trans('Failed to update the Delivery status'),
                    type: 'error',
                })
            },
            onFinish: () => isLoading.value = false,
        }
    )
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #button-new-pallet-delivery="{ linkButton }">
            <Button :style="linkButton.style" :icon="linkButton.icon" :label="linkButton.label" size="l"
                :loading="loading" @click="() => onClickNewPalletDelivery(linkButton)" />
        </template>

        <!-- Column: Reference -->
        <template #cell(reference)="{ item: palletDelivery }">
            <Link :href="palletDeliveryRoute(palletDelivery)" class="primaryLink">
                {{ palletDelivery['reference'] }}
            </Link>
        </template>




        <!-- Column: State -->
        <template #cell(state)="{ item: palletDelivery }">
            <Icon :data="palletDelivery['state_icon']" class="px-1" />
        </template>

        <!-- Column: Estimated Delivery Date -->
        <template #cell(estimated_delivery_date)="{ item: palletDelivery }">
            <div>
                {{ useFormatTime (palletDelivery.estimated_delivery_date) }}
                <span v-if="palletDelivery.state === 'in_process' || palletDelivery.state === 'submitted' || palletDelivery.state === 'confirmed'" class="text-gray-400">
                    ({{useDaysLeftFromToday(palletDelivery.estimated_delivery_date)}})
                </span>
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <Button
                v-if="item.state === 'confirmed'"
                @click="() => onClickReceived(item.receiveRoute)"
                label="receive"
                :loading="isLoading == 'received'"
            />
        </template>
        <template #cell(date)="{ item: customer }">
            <div class="text-gray-500 text-right">{{ useFormatTime(customer["date"], { localeCode: locale.language.code, formatTime: "aiku" }) }}</div>
        </template>
    </Table>

    <Modal :isOpen="openModal" @onClose="openModal = false" width="w-2/4">
        <main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
            <div class="text-center">
                <p class="text-base font-semibold text-indigo-600">403 - Access Denied</p>
                <h1 class="mt-4 text-xl font-bold tracking-tight text-gray-900 sm:text-xl">You do not have permission
                    to
                    access this page</h1>
                <p class="mt-6 text-sm leading-7 text-gray-600">Sorry, the page you are looking for could not be
                    found.
                </p>
                <div class="mt-10 flex items-center justify-center gap-x-6">

                    <div @click="() => openModal = false" class="text-sm font-semibold text-gray-900">Close </div>

                    <div
                        class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Contact Suport<span aria-hidden="true">&rarr;</span>
                    </div>
                </div>
            </div>
        </main>
    </Modal>
</template>
