<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAtom, faBox, faCube, faCloudRainbow, faHamsa, faExclamationTriangle, faPlus, faLink } from "@fal"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"

library.add(faAtom, faBox, faCube, faCloudRainbow, faOctopusDeploy, faHamsa, faExclamationTriangle, faPlus, faLink)

type MissingArtefact = {
    create_route: routeType
    artefacts_route: routeType
    link_route: routeType
    link_data: { org_stock_id: number, trade_unit_id: number }
}

type CompositionRow = {
    code: string
    quantity: number
    org_code?: string
    shop_code?: string
    route?: routeType
    is_made_in_house?: boolean
    artefacts?: { code: string, route: routeType }[]
    missing_artefact?: MissingArtefact | null
}

// The triangle seen from its centre: every row links to the page where that leg is
// actually edited; SKOs made in-house without an artefact can get one from here.
const props = defineProps<{
    data: {
        stocks: CompositionRow[]
        org_stocks: CompositionRow[]
        master_products: CompositionRow[]
        products: CompositionRow[]
    }
    tab: string
}>()

const sections = [
    { key: 'stocks', label: ctrans('Packed in SKOs'), icon: 'fal fa-cloud-rainbow', unit: ctrans('per pack') },
    { key: 'org_stocks', label: ctrans('Packed per warehouse'), icon: 'fal fa-box', unit: ctrans('per pack') },
    { key: 'master_products', label: ctrans('Sold as master products'), icon: 'fab fa-octopus-deploy', unit: ctrans('per outer') },
    { key: 'products', label: ctrans('Sold as products'), icon: 'fal fa-cube', unit: ctrans('per outer') },
] as const

const linkingRow = ref<CompositionRow | null>(null)
const selectedArtefactId = ref<number | null>(null)
const isLinking = ref(false)

const openLinkModal = (row: CompositionRow) => {
    selectedArtefactId.value = null
    linkingRow.value = row
}

const linkArtefact = async () => {
    const missing = linkingRow.value?.missing_artefact
    if (!missing || !selectedArtefactId.value) {
        return
    }
    isLinking.value = true
    try {
        await axios.patch(
            route(missing.link_route.name, { ...missing.link_route.parameters, artefact: selectedArtefactId.value }),
            missing.link_data
        )
        linkingRow.value = null
        router.reload({ only: ['composition'] })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The artefact could not be linked"),
            type: "error",
        })
    } finally {
        isLinking.value = false
    }
}
</script>

<template>
    <div class="max-w-5xl px-4 py-5 sm:px-6 lg:px-8 grid gap-6 sm:grid-cols-2">
        <section v-for="section in sections" :key="section.key" class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-4 py-3 flex items-center gap-2">
                <FontAwesomeIcon :icon="section.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                <h2 class="font-medium text-gray-700">{{ section.label }}</h2>
                <span class="text-xs text-gray-400">({{ (data[section.key] || []).length }})</span>
            </div>
            <div class="px-4 py-3 text-sm max-h-72 overflow-y-auto" style="scrollbar-width: thin">
                <div v-if="!(data[section.key] || []).length" class="text-gray-400 italic">
                    {{ ctrans('None') }}
                </div>
                <div v-for="(row, index) in data[section.key]" :key="index"
                    class="flex flex-wrap items-baseline gap-x-2 gap-y-1 py-0.5"
                    :class="row.missing_artefact ? '-mx-2 px-2 rounded bg-amber-50' : ''">
                    <span v-if="row.org_code" class="w-12 uppercase text-gray-400">{{ row.org_code }}</span>
                    <span v-if="row.shop_code" class="w-12 uppercase text-gray-400">{{ row.shop_code }}</span>
                    <Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="primaryLink">
                        {{ row.code }}
                    </Link>
                    <span v-else class="text-gray-700">{{ row.code }}</span>
                    <span class="text-gray-500">× {{ row.quantity }} {{ section.unit }}</span>
                    <Link v-for="artefact in row.artefacts" :key="artefact.code"
                        :href="route(artefact.route.name, artefact.route.parameters)"
                        class="text-xs text-gray-500 hover:text-gray-700" v-tooltip="ctrans('Artefact')">
                        <FontAwesomeIcon icon="fal fa-hamsa" fixed-width aria-hidden="true" />{{ artefact.code }}
                    </Link>
                    <template v-if="row.missing_artefact">
                        <span class="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800">
                            <FontAwesomeIcon icon="fal fa-exclamation-triangle" aria-hidden="true" />
                            {{ ctrans('No artefact attached') }}
                        </span>
                        <span class="flex gap-1 ml-auto">
                            <Link :href="route(row.missing_artefact.create_route.name, row.missing_artefact.create_route.parameters)">
                                <Button type="tertiary" size="xxs" icon="fal fa-plus" :label="ctrans('Create artefact')" />
                            </Link>
                            <Button type="tertiary" size="xxs" icon="fal fa-link" :label="ctrans('Link artefact')" @click="openLinkModal(row)" />
                        </span>
                    </template>
                </div>
            </div>
        </section>

        <Modal :isOpen="!!linkingRow" @onClose="linkingRow = null" width="w-full max-w-lg">
            <div v-if="linkingRow?.missing_artefact" class="space-y-4">
                <h3 class="text-lg font-medium text-gray-800">
                    {{ ctrans('Link an artefact to :sko', { sko: `${linkingRow.org_code} ${linkingRow.code}` }) }}
                </h3>
                <PureMultiselectInfiniteScroll
                    v-model="selectedArtefactId"
                    :fetchRoute="linkingRow.missing_artefact.artefacts_route"
                    valueProp="id"
                    labelProp="name"
                    :placeholder="ctrans('Search artefacts by code or name')">
                    <template #option="{ option }">
                        <span>{{ option.code }}</span>
                        <span class="ml-2 text-gray-500">{{ option.name }}</span>
                        <span v-if="option.org_stock_code" class="ml-auto text-xs text-amber-700">
                            {{ ctrans('now on :sko', { sko: option.org_stock_code }) }}
                        </span>
                    </template>
                </PureMultiselectInfiniteScroll>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="linkingRow = null" />
                    <Button type="save" :label="ctrans('Link')" :loading="isLinking" :disabled="!selectedArtefactId" @click="linkArtefact" />
                </div>
            </div>
        </Modal>
    </div>
</template>
