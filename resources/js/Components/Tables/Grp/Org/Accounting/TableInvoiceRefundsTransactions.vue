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
import { faSave as falSave, faExclamationCircle } from '@fal'
import { faSave } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
library.add(faSave, falSave, faExclamationCircle)

defineProps<{
    data: object
    tab: string
}>()

const locale = inject('locale', aikuLocaleStructure)

// Section: add refund
// const isLoading = ref<number[]>([])
// const onClickRefund = (routeRefund: routeType, slugRefund: number) => {
//     router[routeRefund.method || 'post'](
//         route(routeRefund.name, routeRefund.parameters),
//         {

//         },
//         {
//             onStart: () => {
//                 isLoading.value?.push(slugRefund)
//             },
//             onFinish: () => {
//                 const index = isLoading.value.indexOf(slugRefund)
//                 if (index > -1) {
//                     isLoading.value.splice(index, 1)
//                 }
//             }
//         }
//     )
// }

// Section: update refund amount
// const isLoadingQuantity = ref<number[]>([])
// const onClickQuantity = (routeRefund: routeType, slugRefund: number, amount: number) => {
//     router[routeRefund.method || 'post'](
//         route(routeRefund.name, routeRefund.parameters),
//         {
//             gross_amount: amount
//         },
//         {
//             onStart: () => {
//                 isLoadingQuantity.value?.push(slugRefund)
//             },
//             onFinish: () => {
//                 const index = isLoadingQuantity.value.indexOf(slugRefund)
//                 if (index > -1) {
//                     isLoadingQuantity.value.splice(index, 1)
//                 }
//             }
//         }
//     )
// }

// const localeCode = navigator.language
</script>

<template>
    <div class="h-min">
        <Table :resource="data" :name="tab">
            <template #cell(net_amount)="{ item }">
                <div :class="item.net_amount < 0 ? 'text-red-500' : ''">
                    {{ locale.currencyFormat(item.currency_code, item.net_amount) }}
                </div>
            </template>

        </Table>
    </div>
</template>
