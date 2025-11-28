<script setup lang="ts">

import { library } from "@fortawesome/fontawesome-svg-core"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { Table as TableTS } from "@/types/Table"
import { faTag, faTrashAlt } from "@fal"
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"
import Tag from "@/Components/Tag.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Link } from "@inertiajs/vue3"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"

import { routeType } from "@/types/route"

import { trans } from "laravel-vue-i18n"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"

library.add(faTag, faTrashAlt)

defineProps<{
    data: TableTS
    tab?: string
    status?: string
}>()


const onUpdateQuantity = (routeUpdate: routeType, id: Number, fulfilment_transaction_id: number, value: number) => {

    value.patch(route(routeUpdate.name, routeUpdate.parameters), {
        preserveScroll: true
    })
}


const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" :is-check-box="false">
        <template #cell(description)="{ item }">
            <!-- edit type : {{ item.edit_type }} -->
            <div v-if="item.description?.model || item.description?.title || item.description?.after_title">
                <span v-if="item.description?.model">{{ item.description.model }}:</span>
                <Link v-if="item.description?.title && item.description.route?.name" :href="route(item.description.route?.name, item.description.route?.parameters)" class="primaryLink">
                    {{ item.description.title }}
                </Link>
                <span v-else>&nbsp;{{ item.description.title }}</span>

                <div v-if="item.description.after_title" class="text-gray-400 italic text-xs">({{ item.description.after_title }})</div>
            </div>

            <div v-else>

            </div>
        </template>

        <!-- Column: asset code -->
        <template #cell(historic_asset_name)="{ item }">
            <div>
                {{ item.historic_asset_code }} <br>
                <span class="text-gray-400">({{ item.historic_asset_name }})</span>
                <span v-if="item.pallet && item.handling_date">
                    <br>
                    <span class="text-gray-400 text-xs">Pallet:
                        <Link :href="route(item.palletRoute?.name, item.palletRoute?.parameters)" class="primaryLink">
                                {{ item.pallet }}
                        </Link>
                    </span>
                    <br>
                    <span class="text-gray-400 text-xs">Date: {{ item.handling_date }}</span>
                </span>
                <span v-else>
                </span>
            </div>
        </template>

        <!-- Column: quantity -->
        <template #cell(quantity)="{ item }">
            <div class="flex justify-end">
                <div v-if="item.edit_type !== 'net'">
                    <NumberWithButtonSave v-model="item.quantity" @onSave="(e)=>onUpdateQuantity(item.updateRoute, item.id,item.fulfilment_transaction_id, e)" />
                </div>
                <div v-else class="text-gray-500">
                    <!-- {{ item.quantity }} -->
                </div>
            </div>
        </template>

        <!-- Column: asset price -->
        <template #cell(historic_asset_price)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item.historic_asset_price || 0) }}/{{ item.historic_asset_unit }}
            <Tag v-if="item['discount'] > 0" :theme="17" noHoverColor>
                <template #label>
                    <font-awesome-icon icon="fal fa-tag" class="text-xs text-emerald-700" />
                    {{ item["discount"] }}%
                </template>
            </Tag>
        </template>

        <template #cell(net_amount)="{ item, proxyItem }">
            <div class="relative">
                <template v-if="item.edit_type == 'net'">
                    <div class="w-72 float-right">
                        <NumberWithButtonSave
                            v-model="proxyItem.net_amount"
                            :saveOnForm="true"
                            :routeSubmit="item.updateRoute"
                            keySubmit="net_amount"
                            :bindToTarget="{
                                mode: 'currency',
                                fluid: true,
                                currency: item.currency_code,
                                locale: 'en-US',
                                step: 0.25
                            }"
                        />
                    </div>
                </template>

                <Transition v-else name="spin-to-right">
                    <span :key="item.net_amount">
                        {{ locale.currencyFormat(item.currency_code, item.net_amount || 0) }}
                    </span>
                </Transition>
            </div>
        </template>

        <!-- Column: Action -->
        <template #cell(actions)="{ item }">
            <ButtonWithLink
                :routeTarget="item.deleteRoute"
                icon="fal fa-trash-alt"
                type="negative"
                v-tooltip="trans('Delete')"
            />
        </template>
    </Table>
</template>