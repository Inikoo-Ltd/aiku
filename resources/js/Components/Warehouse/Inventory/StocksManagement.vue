<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faForklift, faClipboardCheck } from "@fal"
import { faShoppingBasket, faStickyNote } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from 'vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { now } from 'lodash'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faForklift, faClipboardCheck, faShoppingBasket, faStickyNote)

const locale = inject('locale', aikuLocaleStructure)

const selectedShoppingBasket = ref(2)
</script>

<template>
    <div class="max-w-xl mx-auto bg-white shadow-md rounded-lg p-4 space-y-4">
        <!-- Header Section -->
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold flex items-center gap-2">
                <i class="fas fa-box"></i> Active
            </h2>
            <button class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-plus-circle text-xl"></i>
            </button>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-4 gap-2 text-center">
            <div class="bg-gray-100 p-2 rounded">
                <span v-tooltip="trans('Stock in locations')">
                    <FontAwesomeIcon icon="fal fa-inventory" class="text-gray-500" fixed-width aria-hidden="true" />
                </span>
                <span class="ml-2 text-lg font-bold">
                    {{ locale.number(21233 ?? 0) }}
                </span>
            </div>

            <div class="bg-gray-100 p-2 rounded">
                <span v-tooltip="trans('Reserved paid parts in process by customer services')">
                    <FontAwesomeIcon icon="fal fa-shopping-cart" class="text-gray-500" fixed-width aria-hidden="true" />
                </span>
                <span class="ml-2 text-lg font-bold">
                    {{ locale.number(43 ?? 0) }}
                </span>
            </div>

            <div class="bg-gray-100 p-2 rounded">
                <span v-tooltip="trans('Parts been picked')">
                    <FontAwesomeIcon icon="fal fa-shopping-basket" class="text-gray-500" fixed-width aria-hidden="true" />
                </span>
                <span class="ml-2 text-lg font-bold">
                    {{ locale.number(1112 ?? 0) }}
                </span>
            </div>

            <div class="bg-gray-100 p-2 rounded text-red-600">
                <!-- <span xv-tooltip="trans('Stocks in the locaiton')">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600" fixed-width aria-hidden="true" />
                </span> -->
                <span v-tooltip="trans('Stock available for sale')" class="ml-2 text-lg font-bold">
                    {{ locale.number(4444 ?? 0) }}
                </span>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="border-t pt-2">
            <p class="font-semibold text-gray-600">Out of stock</p>
        </div>

        <!-- Out of Stock -->
        <div class="border-t pt-2">
            <div class="grid grid-cols-7 gap-x-3">
                <div class="col-span-2 font-semibold text-gray-600">Stock value:</div>
                <div class="col-span-3 text-right">
                    4720000
                </div>
                <div class="col-span-2 text-right">
                    8000 /SKO
                </div>
            </div>
            <div class="grid grid-cols-7 gap-x-3">
                <div class="col-span-2 font-semibold text-gray-600">Current cost:</div>
                <div class="col-span-3 text-right">
                    4720000
                </div>
                <div class="col-span-2 text-right">
                    8000 /SKO
                </div>
            </div>
        </div>

        <!-- Location Grid -->
        <div class="border-t pt-2 gap-2 items-center text-gray-700">
            <div v-for="xx in 5" class="grid grid-cols-7 gap-x-3 items-center gap-2">
                <div class="col-span-5 flex items-center gap-x-2">
                    <div @click="() => console.log('qq sticnote')"
                        v-tooltip="trans('Add part\'s location note')"
                        class="cursor-pointer"
                        :class="selectedShoppingBasket === xx ? 'text-blue-700' : 'text-gray-400 hover:text-gray-700'"
                    >
                        <FontAwesomeIcon
                            :icon="selectedShoppingBasket === xx ? 'fas fa-sticky-note' : 'fal fa-sticky-note'"
                            class=""
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>

                    <div @click="() => selectedShoppingBasket = xx"
                        v-tooltip="trans('Add part\'s location note')"
                        class="cursor-pointer "
                        :class="selectedShoppingBasket === xx ? 'text-blue-700' : 'text-gray-400 hover:text-gray-700'"
                    >
                        <FontAwesomeIcon
                            :icon="selectedShoppingBasket === xx ? 'fas fa-shopping-basket' : 'fal fa-shopping-basket'"
                            class=""
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>
                    <span>E3</span>
                    <i class="fas fa-question-circle text-gray-400"></i>
                </div>

                <div v-tooltip="trans('Last audit :date', { date: useFormatTime(new Date()) })" class="text-right">
                    0
                    <FontAwesomeIcon icon="fal fa-clock" class="text-gray-400" fixed-width aria-hidden="true" />
                </div>

                <div class="text-right">
                    6
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between xborder-t xpt-3">
            <Button
                iconRight="fal fa-clipboard-check"
                :label="trans('Stock check')"
                size="sm"
                type="tertiary"
            />

            <Button
                iconRight="fal fa-forklift"
                :label="trans('Move stock')"
                size="sm"
                type="tertiary"
            />

            <Button
                iconRight="fal fa-edit"
                :label="trans('Edit locations')"
                size="sm"
                type="tertiary"
            />
        </div>
    </div>
</template>