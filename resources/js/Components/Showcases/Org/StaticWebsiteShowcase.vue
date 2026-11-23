<!--
  - Author Louis Perez
  - Created on 23-11-2026-11h-20m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFragile, faGlobe, faHome, faInfoCircle, faPuzzlePiece } from "@fal"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import RealUserSpeed from "@/Components/DataDisplay/RealUserSpeed.vue"
import StaticWebsiteDetails from "@/Components/Web/StaticWebsiteDetails.vue"
import { ctrans } from "@/Composables/useTrans"
import { StaticWebsiteShowcaseData } from "@/types/StaticWebsiteShowcase"

defineProps<{
    data: StaticWebsiteShowcaseData
    realUserSpeedHistory?: object | null
}>()
</script>

<template>
    <div class="px-6 pt-4 pb-12 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_20rem] gap-6">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <a :href="data.url" target="_blank" rel="noopener" v-tooltip="data.url_hint"
                        class="bg-white hover:bg-gray-50 ring-1 ring-gray-300 cursor-pointer rounded overflow-hidden flex text-xxs md:text-base text-gray-500">
                        <div class="bg-gray-200 py-2 px-2">
                            <FontAwesomeIcon :icon="faGlobe" class="px-1" fixed-width aria-hidden="true" />
                        </div>
                        <div class="flex items-center px-4">
                            {{ data.url }}
                        </div>
                    </a>
                </div>

                <div class="border-t border-gray-300 mt-6 pt-4">
                    <StaticWebsiteDetails :items="data.details" />
                </div>

                <div class="border-t border-gray-300 mt-6 pt-4">
                    <div class="font-semibold w-fit text-lg mb-2">
                        {{ ctrans("Page Speed") }}
                    </div>
                    <RealUserSpeed :report="realUserSpeedHistory" :data-sources="['crux']" />
                </div>
            </div>

            <div class="flex justify-end">
                <div class="w-64 border border-gray-300 rounded-md p-2 h-fit">
                    <div v-if="data.route_storefront" class="p-2">
                        <ButtonWithLink :routeTarget="data.route_storefront" :icon="faHome" type="tertiary"
                            :label="ctrans('Storefront')" full />
                    </div>

                    <hr v-if="data.route_header || data.route_footer">

                    <div v-if="data.route_header" class="px-2 pt-2 pb-1">
                        <ButtonWithLink :routeTarget="data.route_header" :icon="faPuzzlePiece" type="secondary"
                            :label="ctrans('Header')" full />
                    </div>

                    <div v-if="data.route_footer" class="px-2 pt-1 pb-2">
                        <ButtonWithLink :routeTarget="data.route_footer" :icon="faPuzzlePiece" type="secondary"
                            :label="ctrans('Footer')" full />
                    </div>

                    <div v-if="data.can_edit" class="p-2 space-y-2">
                        <ModalConfirmationDelete
                            :description="ctrans('Purge all cached files. Purging your cache may slow your website temporarily')"
                            :title="ctrans('Break cache')" :noLabel="ctrans('Confirm')" noIcon="" :routeDelete="{
                                name: 'grp.models.static_website.break_cache',
                                parameters: {
                                    staticWebsite: data.id
                                },
                                method: 'post'
                            }">
                            <template #default="{ changeModel }">
                                <ButtonWithLink @click="changeModel" :icon="faFragile" type="tertiary"
                                    :label="ctrans('Break cache')" full>
                                    <template #iconRight>
                                        <div v-tooltip="ctrans('If you made some changes but did not updated yet in the website, use this feature')"
                                            class="text-gray-400 hover:text-gray-700">
                                            <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                                        </div>
                                    </template>
                                </ButtonWithLink>
                            </template>
                        </ModalConfirmationDelete>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
