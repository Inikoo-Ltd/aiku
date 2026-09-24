<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 04 Sep 2023 10:22:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// This file is used on CustomerApp, PublicApp

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLanguage, faMoneyBillAlt, faLayerGroup, faPoundSign } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faLanguage, faMoneyBillAlt, faLayerGroup, faPoundSign, faSpinnerThird)

import FooterTab from '@/Components/Footer/FooterTab.vue'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import LoadingText from '@/Components/Utils/LoadingText.vue'
import { inject, ref } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

const isLoadingCurrency = ref(false)
const onSelectCurrency = (currency) => {
    selectedCurrency.value = currency
    // router.post(route('grp.models.profile.update'), {
    //     preferred_currency_id: '451'
    // }, {
    //     onStart: () => isLoadingCurrency.value = currency.id,
    //     onFinish: () => isLoadingCurrency.value = false,
    //     preserveScroll: true
    // })
    
}


const currencyList = [
    {
        label: 'Organisation',
        symbol: 'fal fa-pound-sign'
    },
    {
        label: 'Shop',
        symbol: 'fal fa-layer-group'
    },
    {
        label: 'Group',
        symbol: 'fal fa-dollar'
    }
]
const selectedCurrency = ref(currencyList[0])
</script>

<template>
    <Popover v-slot="{ open }" class="relative h-full">
        <PopoverButton :class="open ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 text-gray-200'"
            class="group inline-flex items-center px-3 h-full font-medium gap-x-1">
            <FontAwesomeIcon icon='fal fa-money-bill-alt' class='' fixed-width aria-hidden='true' />
            <div class="h-full font-extralight text-xs flex items-center leading-none">
                Organisation (<FontAwesomeIcon :icon='selectedCurrency.symbol' class='' fixed-width aria-hidden='true' />)
            </div>

        </PopoverButton>

        <transition name="headlessui">
            <PopoverPanel class="absolute bottom-full right-0 z-10 sm:px-0">
                <FooterTab tabName="language" :header="false">
                    <template #default>
                        <div v-if="true">
                            <div v-for="(currency, index) in currencyList"
                                @click="() => onSelectCurrency(currency)"
                                class="py-1.5 hover:bg-white/20 cursor-pointer text-gray-200"
                            >
                                {{ currency.label }}
                            </div>
                        </div>

                        <div v-else class="grid pt-2.5 pb-1.5">{{ trans('Nothing to show here') }}</div>
                    </template>
                </FooterTab>
            </PopoverPanel>
        </transition>
    </Popover>
</template>
