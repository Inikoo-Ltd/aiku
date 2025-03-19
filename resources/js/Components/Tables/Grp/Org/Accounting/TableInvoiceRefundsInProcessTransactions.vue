<script setup lang='ts'>

import Table from '@/Components/Table/Table.vue'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PureInput from '@/Components/Pure/PureInput.vue'
import InputNumber from 'primevue/inputnumber'
import { get, set } from 'lodash-es'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSave as falSave, faExclamationCircle, faMinus, faPlus, faArrowCircleLeft } from '@fal'
import { faSave } from '@fad'
import { faArrowAltCircleLeft } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
library.add(faArrowAltCircleLeft, faSave, falSave, faExclamationCircle, faMinus, faPlus, faArrowCircleLeft)

defineProps<{
    data: object
    tab: string
}>()

const locale = inject('locale', aikuLocaleStructure)

// Section: update refund amount
const isLoadingQuantity = ref<number[]>([])
const onClickQuantity = (routeRefund: routeType, slugRefund: number, amount: number) => {
    router[routeRefund.method || 'post'](
        route(
            routeRefund.name, 
            routeRefund.parameters
        ),
        {
            net_amount: amount
        },
        {
            preserveScroll: true,
            onStart: () => {
                isLoadingQuantity.value?.push(slugRefund)
            },
            onFinish: () => {
                const index = isLoadingQuantity.value.indexOf(slugRefund)
                if (index > -1) {
                    isLoadingQuantity.value.splice(index, 1)
                }
            }
        }
    )
}

const localeCode = navigator.language
</script>

<template>
    <div class="h-min">
        <Table :resource="data" :name="tab">
            <template #cell(net_amount)="{ item }">
                <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
                    {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
                </div>
            </template>

            <template #cell(action)="{ item, proxyItem }">
                <!-- <pre>{{ item.data }}</pre> -->
                <!-- <pre>new: {{ item.new_refund_amount }}</pre>
                ------<br> -->
                <!-- <Button
                    v-if="!item.in_process"
                    @click="onClickRefund(item.refund_route, item.code)"
                    :label="trans('Refund')"
                    icon="fal fa-plus"
                    type="secondary"
                    :loading="isLoading.includes(item.code)"
                /> -->
                <div class="space-x-2 w-[350px]">
                    <ButtonWithLink
                        v-if="!get(proxyItem, ['refund_net_amount'], 0) && get(proxyItem, ['refund_net_amount'], 0) != item.net_amount"
                        @xclick="() => get(proxyItem, 'refund_type', null) == 'full' ? set(proxyItem, 'refund_type', null): set(proxyItem, 'refund_type', 'full')"
                        :key="item.code"
                        :routeTarget="item.refund_transaction_full_refund"
                        :label="trans('Refund item')"
                        icon="fas fa-arrow-alt-circle-left"
                        size="s"
                        :bindToLink="{ preserveScroll: true }"
                        type="secondary"
                        :xtype="get(proxyItem, 'refund_type', null) == 'full' ? 'black' : 'secondary'"
                    />
                    
                    <Button
                        v-if="!get(proxyItem, ['refund_net_amount'], 0)"
                        @click="() => get(proxyItem, 'refund_type', null) == 'partial' ? set(proxyItem, 'refund_type', null): set(proxyItem, 'refund_type', 'partial')"
                        :key="get(proxyItem, 'refund_type', null) + '-' + item.code"
                        :label="trans('Refund Item Partially')"
                        icon="fal fa-arrow-circle-left"
                        size="s"
                        :bindToLink="{ preserveScroll: true }"
                        :type="get(proxyItem, 'refund_type', null) == 'partial' ? 'gray' : 'tertiary'"
                    />
                </div>

                <Transition name="slide-to-left">
                    <div v-show="get(proxyItem, 'refund_type', null) == 'partial' || get(proxyItem, ['refund_net_amount'], 0)" class="w-fit flex items-center gap-x-1 mt-2">
                        <div>
                            <InputNumber
                                :modelValue="get(proxyItem, ['new_refund_amount'], get(proxyItem, ['refund_net_amount'], 0))"
                                @input="(e) => (console.log(e.value), set(proxyItem, ['new_refund_amount'], e.value))"
                                @update:model-value="(e) => set(proxyItem, ['new_refund_amount'], e)"
                                :class="get(proxyItem, ['new_refund_amount'], null) > item.net_amount ? 'errorShake' : ''"
                                inputClass="width-12"
                                :max="Number(item.net_amount)"
                                :min="0"
                                placeholder="0"
                                mode="currency"
                                :currency="item.currency_code"
                                :locale="localeCode"
                                showButtons buttonLayout="horizontal"
                                :step="item.unit_price"
                                size="small"
                            >
                                <template #decrementicon>
                                    <FontAwesomeIcon icon="fal fa-minus" aria-hidden="true" />
                                </template>
                                <template #incrementicon>
                                    <FontAwesomeIcon icon="fal fa-plus" aria-hidden="true" />
                                </template>
                            </InputNumber>
                    
                            <p v-if="get(proxyItem, ['new_refund_amount'], null) > item.net_amount" class="italic text-red-500 text-xs mt-1">
                                <!-- <FontAwesomeIcon icon='fal fa-exclamation-circle' class='' fixed-width aria-hidden='true' /> -->
                                {{ trans('Refund amount should not over the net amount') }}
                            </p>
                        </div>
                        <!-- {{ get(proxyItem, ['new_refund_amount'], null) > item.net_amount }} -->
                        <LoadingIcon v-if="isLoadingQuantity.includes(item.rowIndex)" class="h-8" />
                        <FontAwesomeIcon
                            v-else-if="get(proxyItem, ['new_refund_amount'], null) ? proxyItem.new_refund_amount !== (proxyItem.refund_amount || 0) : false"
                            @click="() => onClickQuantity(item.refund_route, item.rowIndex, get(proxyItem, ['new_refund_amount'], 0))"
                            icon="fad fa-save"
                            class="h-8 cursor-pointer"
                            :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }"
                            aria-hidden="true"
                        />
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </div>
                </Transition>
            </template>
        </Table>
    </div>
</template>
