<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 07 Oct 2022 09:34:00 Central European Summer Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faMapSigns, faPallet, faTruckCouch, faFilePdf, faUpload, faWarehouse, faEmptySet, faMoneyBillWave } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import MetaLabel from "@/Components/Headings/MetaLabel.vue"
import Container from "@/Components/Headings/Container.vue"
import Action from "@/Components/Forms/Fields/Action.vue"
import SubNavigation from "@//Components/Navigation/SubNavigation.vue"
import { kebabCase } from "lodash"
import Button from "@/Components/Elements/Buttons/Button.vue"
import {faNarwhal, faReceipt} from "@fas"
import { faLayerPlus } from "@far"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { inject, ref } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useTruncate } from '@/Composables/useTruncate'

library.add(faTruckCouch, faUpload, faFilePdf, faMapSigns, faNarwhal, faReceipt, faLayerPlus, faPallet, faWarehouse, faEmptySet, faMoneyBillWave)

const props = defineProps<{
    data: PageHeadingTypes
    dataToSubmit?: any
    dataToSubmitIsDirty?: any
}>()

const isButtonLoading = ref<boolean | string>(false)

if (props.dataToSubmit && props.data.actionActualMethod) {
    props.dataToSubmit["_method"] = props.data.actionActualMethod
}

const originUrl = location.origin
const layout = inject('layout', layoutStructure)


</script>


<template>
    <!-- Sub Navigation -->
    <SubNavigation v-if="data.subNavigation?.length" :dataNavigation="data.subNavigation" />


    <slot name="afterSubNav">

    </slot>

    <div class="relative px-4 py-2 md:pb-2 md:pt-2 lg:py-2 grid grid-flow-col justify-between items-center">
        <div class="flex items-end gap-x-3">

            <!-- Section: Main Title -->
            <div class="flex leading-none py-1.5 items-center gap-x-2 font-bold text-gray-700 text-2xl tracking-tight ">
                <div v-if="data.container" class="text-slate-500 text-lg">
                    <Link v-if="data.container.href"
                        :href="route(data.container.href['name'], data.container.href['parameters'])">
                    <Container :data="data.container" />
                    </Link>
                    <div v-else class="flex items-center gap-x-1">
                        <Container :data="data.container" />
                    </div>
                </div>

                <div v-if="data.icon" class="inline text-gray-400">
                    <slot name="mainIcon">
                        <FontAwesomeIcon
                            v-tooltip="data.icon.tooltip || ''"
                            aria-hidden="true"
                            :icon="data.icon.icon || data.icon"
                            size="sm"
                            fixed-width
                        />
                    </slot>
                </div>

                <div class="flex flex-col sm:flex-row gap-y-1.5 gap-x-3 sm:items-center ">
                    <h2 :class="data.noCapitalise ? '' : 'capitalize'" class="space-x-2">
                        <span v-if="data.model" class="text-gray-400 font-medium">{{ data.model }}</span>
                        <span class="">{{ useTruncate(data.title, 30) }}</span>
                    </h2>

                    <!-- Section: After Title -->
                    <slot name="afterTitle">
                        <div v-if="data.iconRight || data.afterTitle" class="flex gap-x-2 items-center">
                            <FontAwesomeIcon v-if="data.iconRight" v-tooltip="data.iconRight.tooltip || ''"
                                :icon="data.iconRight?.icon || data.iconRight" class="h-4" :class="data.iconRight.class"
                                aria-hidden="true" />
                            <div v-if="data.afterTitle" class="text-gray-400 font-normal text-lg leading-none">
                                {{ data.afterTitle.label }}
                            </div>
                        </div>
                    </slot>
                </div>
            </div>

            <!-- Section: mini Tabs -->
            <div class="mb-2 block h-full">
                <div v-if="data.meta?.length"
                    class="w-fit flex flex-col sm:mt-0 sm:flex-row items-end sm:flex-wrap sm:gap-x-0.5 sm:gap-y-0.5 text-gray-500 text-sm">
                    <template v-for="item in data.meta">
                        <slot :name="`tabs-${item.key}`" :data="item">
                            <component :is="item.route?.name ? Link : 'div'"
                                :href="item.route?.name ? route(item.route.name, item.route.parameters) : '#'" :class="[
                                    item.route?.name
                                        ? $page.url.startsWith((route(item.route.name, item.route.parameters)).replace(new RegExp(originUrl, 'g'), ''))
                                            ? 'text-gray-500 font-medium'
                                            : 'text-gray-500 hover:text-gray-800'
                                        : 'text-gray-500'
                                ]" class="group first:pl-0 px-1 flex gap-x-1 items-center">
                                <FontAwesomeIcon v-if="item.leftIcon" :title="item.leftIcon.tooltip" fixed-width aria-hidden="true" :icon="item.leftIcon.icon" class="opacity-70 group-hover:opacity-100" />
                                <MetaLabel :item="item" class="leading-none" />
                            </component>
                        </slot>
                    </template>
                </div>
            </div>

        </div>

        <!-- Section: Button and/or ButtonGroup -->
        <slot name="button" :dataPageHead="{ ...props }">
            <div class="flex flex-col items-end sm:flex-row flex-wrap justify-end sm:items-center gap-y-1 gap-x-2 rounded-md">
                <slot name="otherBefore" :dataPageHead="{ ...props }" />

                <template v-for="(action, actIndex) in data.actions">
                    <template v-if="action">
                        <!-- Button -->
                        <slot v-if="action.type == 'button'"
                            :name="`button-${kebabCase(action.key ? action.key : action.label)}`" :action="action">
                            <slot :name="`button-index-${actIndex}`" :action="action">
                                <Action v-if="action" :action="action" :dataToSubmit="dataToSubmit" />
                            </slot>
                        </slot>

                        <!-- ButtonGroup -->
                        <slot v-if="action.type == 'buttonGroup' && action.button?.length"
                            :name="`button-group-${action.key}`" :action="action">
                            <div class="rounded-md flex flex-wrap justify-end gap-y-1" :class="[
                                (action.button?.length || 0) > 1 ? '' : '',
                            ]" :style="{
                                // border: `1px solid ${action?.button?.length > 1 ? layout?.app?.theme[4] + '88' : 'transparent'}`
                            }">
                                <slot v-for="(button, index) in action.button"
                                    :name="`button-group-${kebabCase(button.key ? button.key : button.label)}`"
                                    :action="button">
                                    <component :key="'buttonPH' + index + button.label"
                                        :is="button.route?.name ? Link : 'div'"
                                        :href="button.route?.name ? route(button.route.name, button.route.parameters) : '#'"
                                        class="" :method="button.route?.method || 'get'"
                                        @start="() => isButtonLoading = 'buttonGroup' + index"
                                        @finish="() => button.fullLoading ? false : isButtonLoading = false"
                                        @error="() => button.fullLoading ? isButtonLoading = false : false"
                                        :as="button.target ? 'a' : 'div'" :target="button.target">
                                        <Button :style="button.style" :label="button.label" :icon="button.icon"
                                            :loading="isButtonLoading === 'buttonGroup' + index"
                                            :iconRight="button.iconRight" :disabled="button.disabled"
                                            :key="`ActionButton${button.label}${button.style}`"
                                            :tooltip="button.tooltip"
                                            class="inline-flex items-center h-full rounded-none text-sm border-none font-medium shadow-sm focus:ring-transparent focus:ring-offset-transparent focus:ring-0"
                                            :class="{ 'rounded-l-md': index === 0, 'rounded-r-md ': index === action.button?.length - 1 }">
                                        </Button>
                                    </component>
                                </slot>
                            </div>
                        </slot>
                    </template>
                </template>
                <slot name="other" :dataPageHead="{ ...props }" />
            </div>
        </slot>

    </div>
    <hr class="border-gray-300" />
</template>
