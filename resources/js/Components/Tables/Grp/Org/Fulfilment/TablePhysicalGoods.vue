<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot } from '@fal'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Button from "@/Components/Elements/Buttons/Button.vue";
import {ref} from "vue";

library.add(faRobot)


const props = defineProps<{
    data: {}
    state?: string
    tab?: string
}>()

const isActionLoading = ref<string | boolean>(false)
const emits = defineEmits<{
    (e: 'renderTableKey'): void
}>()

function physicalGoodsRoute(product: {}) {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.catalogue.physical_goods.index':
            return route(
                'grp.org.fulfilments.show.catalogue.physical_goods.show',
                [route().params['organisation'], route().params['fulfilment'], product.slug])
        default:
            return null
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: service }">
            <!-- <Link :href="physicalGoodsRoute(service)" class="primaryLink">
                {{ service['code'] }}
            </Link> -->
            <component :is="physicalGoodsRoute(service) ? Link : 'div'" :href="physicalGoodsRoute(service) || '#'" :class="physicalGoodsRoute(service) ? 'primaryLink' : ''">
                {{ service['code'] }}
            </component>
        </template>

        <!-- Column: Shop Code -->
        <template #cell(shop_code)="{ item: service }">
            <Link v-if="service['shop_slug']" :href="physicalGoodsRoute(service)" class="secondaryLink">
                {{ service['shop_slug'] }}
            </Link>
        </template>

        <!-- Column: Icon -->
        <template #cell(state)="{ item: service }">
            <Icon :data="service['state_icon']" />
        </template>

        <!-- Column: Price -->
        <template #cell(price)="{ item: service }">
            {{ useLocaleStore().currencyFormat(service['currency_code'], service['price']) }} /{{
                service['unit_abbreviation'] }}
        </template>

        <!-- Column: Total -->
        <template #cell(total)="{ item: service }">
            {{ useLocaleStore().currencyFormat(service['currency_code'], service['total']) }}
        </template>

        <!-- Column: Workflow -->
        <template #cell(workflow)="{ item: service }">
            <template v-if="service['auto_assign_asset']">
                <FontAwesomeIcon icon='fal fa-robot' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                {{ service['auto_assign_asset'] }}: {{ service['auto_assign_asset_type'] }}
            </template>
        </template>

        <template #cell(actions)="{ item: service }">
            <div v-if="props.state == 'in_process'">
                <Link
                    :href="service.deletePhysicalGoodRoute?.name ? route(service.deletePhysicalGoodRoute.name, service.deletePhysicalGoodRoute.parameters) : '#'"
                    method="delete"
                    as="div"
                    :onStart="() => isActionLoading = 'delete' + service.id"
                    :onSuccess="() => emits('renderTableKey')"
                    :onFinish="() => isActionLoading = false"
                    v-tooltip="'Unselect this physical good'"
                    class="w-fit"
                >
                    <Button :key="'physicalGoodsDelete' + (isActionLoading == 'delete' + service.id)" icon="far fa-trash-alt" :loading="isActionLoading == 'delete' + service.id" type="negative" />
                </Link>
            </div>
        </template>
    </Table>
</template>
