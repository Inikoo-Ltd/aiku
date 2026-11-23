<!--
  - Author Louis Perez
  - Created on 23-11-2026-13h-15m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import BrowserView from "@/Components/Pure/BrowserView.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import RealUserSpeed from "@/Components/DataDisplay/RealUserSpeed.vue"
import WebpageSeo from "@/Components/DataDisplay/WebpageSeo.vue"
import StaticWebsiteDetails from "@/Components/Web/StaticWebsiteDetails.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBrowser, faDesktop, faFragile, faMobileAlt, faPuzzlePiece, faTabletAlt } from "@fal"
import { StaticWebpageShowcaseData } from "@/types/StaticWebsiteShowcase"

library.add(faBrowser, faDesktop, faMobileAlt, faPuzzlePiece, faTabletAlt)

defineProps<{
    data: StaticWebpageShowcaseData
    realUserSpeed?: object | null
}>()

const screenMode = ref<"desktop" | "tablet" | "mobile">("desktop")
const isIframeLoading = ref(true)

const screenModeOptions = [
    { label: ctrans("Desktop"), value: "desktop", icon: ["fal", "desktop"] },
    { label: ctrans("Tablet"), value: "tablet", icon: ["fal", "tablet-alt"] },
    { label: ctrans("Mobile"), value: "mobile", icon: ["fal", "mobile-alt"] },
]
</script>

<template>
    <div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-6 py-3">
                    <span class="text-sm font-semibold text-gray-800">{{ ctrans("Preview") }}</span>
                    <div class="flex items-center gap-2">
                        <ModalConfirmationDelete
                            v-if="data.can_edit"
                            :description="ctrans('Purge all cached files. Purging your cache may slow your website temporarily')"
                            :title="ctrans('Break cache')" :noLabel="ctrans('Confirm')" noIcon="" :routeDelete="{
                                name: 'grp.models.static_webpage.break_cache',
                                parameters: {
                                    webpage: data.id
                                },
                                method: 'post'
                            }">
                            <template #default="{ changeModel }">
                                <Button v-tooltip="ctrans('Break cache')" @click="changeModel" type="tertiary" size="xs" :icon="faFragile" :aria-label="ctrans('Break cache')" />
                            </template>
                        </ModalConfirmationDelete>
                        <SegmentedToggle v-model="screenMode" :options="screenModeOptions" :aria-label="ctrans('Screen size')" />
                    </div>
                </div>

                <div class="p-6">
                    <BrowserView :screenMode="screenMode" :tab="{ icon: 'fal fa-browser', label: data.title ?? data.path }"
                        :url="{ domain: data.domain, page: data.path === '/' ? '' : data.path.replace(/^\//, '') }">
                        <template #page>
                            <div class="relative w-full h-full">
                                <div v-if="isIframeLoading" class="absolute inset-0 flex items-center justify-center bg-white">
                                    <LoadingIcon class="w-24 h-24 text-6xl" />
                                </div>
                                <iframe
                                    :src="data.preview_url"
                                    :key="screenMode"
                                    :title="data.title ?? data.path"
                                    class="w-full h-full"
                                    @load="isIframeLoading = false" />
                            </div>
                        </template>
                    </BrowserView>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <RealUserSpeed embedded :report="realUserSpeed" :data-sources="['crux']" />
                </div>

                <StaticWebsiteDetails :items="data.details" />

                <div v-if="data.shared_parts?.length" class="rounded-xl bg-white p-4 text-sm ring-1 ring-gray-200">
                    <div class="text-gray-500">{{ ctrans("This page shows the site's shared parts") }}</div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <Link v-for="part in data.shared_parts" :key="part.label" :href="route(part.route.name, part.route.parameters)">
                            <Button type="tertiary" size="xs" icon="fal fa-puzzle-piece" :label="part.label" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <WebpageSeo :seo="data.seo" :structuredDataSource="null" />
    </div>
</template>
