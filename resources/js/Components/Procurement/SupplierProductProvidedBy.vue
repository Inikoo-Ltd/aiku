<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-19 09:42
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import Menu from "primevue/menu"
import type { MenuItem } from "primevue/menuitem"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProductImageViewerDialog from "@/Components/Product/ProductImageViewerDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStar, faCheck } from "@fal"

library.add(faStar, faCheck)

const props = defineProps<{
    parties: {
        type?: string
        label: string
        icon: string
        name: string | null
        code: string | null
        image: object | null
        route: routeType
    }[]
    otherSuppliers: {
        supplier_code: string
        supplier_name: string
        image: object | null
        id: number
        code: string
        is_available: boolean
        can_be_preferred?: boolean
        preferred_in?: string[]
        supplier_route: routeType
        route: routeType | null
    }[]
    preferredIn?: string[]
    productCode?: string
    setPreferred?: {
        route: routeType
        supplier_product_id: number
        can_be_preferred: boolean
        organisations: { id: number; code: string }[]
    } | null
    compact?: boolean
}>()

const preferredLabel = (organisationCodes: string[]) => ctrans("Preferred in :organisations", { organisations: organisationCodes.join(", ") })

const preferredTooltip = (productCode: string | null | undefined, organisationCodes: string[], supplierName: string | null) =>
    ctrans("Purchase orders for :product from :organisations will be produced by :supplier", {
        product: productCode ?? "",
        organisations: organisationCodes.join(", "),
        supplier: supplierName ?? "",
    })

const preferredBadgeClass = "mt-1 inline-flex items-center gap-1 rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs font-medium text-[var(--app-accent-strong)]"

const preferredMenu = ref<InstanceType<typeof Menu> | null>(null)
const preferredMenuItems = ref<MenuItem[]>([])
const savingId = ref<number | null>(null)
const result = ref<{ id: number; message: string; tone: "done" | "skipped" | "error" } | null>(null)

const makePreferred = async (supplierProductId: number, organisationId: number | null) => {
    if (!props.setPreferred) {
        return
    }

    savingId.value = supplierProductId
    result.value = null
    try {
        const response = await axios.post(route(props.setPreferred.route.name, props.setPreferred.route.parameters), {
            supplier_product_id: supplierProductId,
            organisation_id: organisationId,
        })
        result.value = { id: supplierProductId, message: response.data.message, tone: response.data.updated?.length ? "done" : "skipped" }
        router.reload({ only: ["showcase"] })
    } catch (error: any) {
        const errors = error?.response?.data?.errors
        result.value = { id: supplierProductId, message: errors ? Object.values(errors).flat().join(" ") : ctrans("The preferred supplier could not be changed."), tone: "error" }
    } finally {
        savingId.value = null
    }
}

const openPreferredMenu = (event: Event, supplierProductId: number, preferredOrganisations: string[]) => {
    const organisations = props.setPreferred?.organisations ?? []

    preferredMenuItems.value = [
        {
            label: ctrans("All organisations"),
            disabled: organisations.every((organisation) => preferredOrganisations.includes(organisation.code)),
            command: () => makePreferred(supplierProductId, null),
        },
        { separator: true },
        ...organisations.map((organisation) => ({
            label: preferredOrganisations.includes(organisation.code) ? ctrans(":organisation (preferred)", { organisation: organisation.code }) : organisation.code,
            disabled: preferredOrganisations.includes(organisation.code),
            command: () => makePreferred(supplierProductId, organisation.id),
        })),
    ]
    preferredMenu.value?.toggle(event)
}

const viewerImages = ref<{ source: object; alt?: string }[]>([])
const isImageViewerVisible = ref(false)

const openImageViewer = (image: object, alt: string | null) => {
    viewerImages.value = [{ source: image, alt: alt ?? undefined }]
    isImageViewerVisible.value = true
}

const supplierParties = computed(() => props.parties.filter((party) => party.type !== "agent"))
const agentParties = computed(() => props.parties.filter((party) => party.type === "agent"))

const gridClass = props.compact ? "grid gap-2" : "grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
</script>

<template>
    <div>
        <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
            <Icon :data="{ icon: 'fal fa-person-dolly' }" />
            {{ ctrans("Provided by") }}
        </h3>
        <div v-if="agentParties.length" :class="gridClass">
            <component :is="party.route ? Link : 'div'" v-for="party in agentParties" :key="party.label"
                :href="party.route ? route(party.route.name, party.route.parameters) : undefined"
                class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                <button v-if="party.image" type="button" class="h-12 w-12 shrink-0 cursor-zoom-in overflow-hidden rounded" v-tooltip="ctrans('View image')"
                    @click.prevent.stop="openImageViewer(party.image, party.name)">
                    <Image :src="party.image" imageCover class="h-full w-full" />
                </button>
                <Icon v-else :data="{ icon: party.icon }" class="w-12 shrink-0 text-center text-2xl text-gray-400" />
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                    <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                </div>
                <span class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-400">{{ party.code }}</span>
            </component>
        </div>

        <div :class="[gridClass, { 'mt-2': agentParties.length }]">
            <component :is="party.route ? Link : 'div'" v-for="party in supplierParties" :key="party.label"
                :href="party.route ? route(party.route.name, party.route.parameters) : undefined"
                class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                <button v-if="party.image" type="button" class="h-12 w-12 shrink-0 cursor-zoom-in overflow-hidden rounded" v-tooltip="ctrans('View image')"
                    @click.prevent.stop="openImageViewer(party.image, party.name)">
                    <Image :src="party.image" imageCover class="h-full w-full" />
                </button>
                <Icon v-else :data="{ icon: party.icon }" class="w-12 shrink-0 text-center text-2xl text-gray-400" />
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                    <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                    <span v-if="party.type === 'supplier' && preferredIn?.length" :class="preferredBadgeClass" v-tooltip="preferredTooltip(productCode, preferredIn, party.name)">
                        <Icon :data="{ icon: 'fal fa-star' }" />
                        {{ preferredLabel(preferredIn) }}
                    </span>
                    <template v-if="party.type === 'supplier' && setPreferred?.can_be_preferred && setPreferred.organisations.length">
                        <div class="mt-1">
                            <Button type="tertiary" size="xxs" :label="ctrans('Set as preferred')" :loading="savingId === setPreferred.supplier_product_id"
                                aria-haspopup="true" aria-controls="provided-by-preferred-menu"
                                @click.prevent.stop="(event: Event) => openPreferredMenu(event, setPreferred!.supplier_product_id, preferredIn ?? [])">
                                <template #icon>
                                    <Icon :data="{ icon: 'fal fa-check' }" class="text-green-600" />
                                </template>
                            </Button>
                        </div>
                        <div v-if="result?.id === setPreferred.supplier_product_id" class="mt-1 text-xs" :class="{ 'text-red-600': result.tone === 'error', 'text-amber-600': result.tone === 'skipped', 'text-green-700': result.tone === 'done' }">{{ result.message }}</div>
                    </template>
                </div>
                <span class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-400">{{ party.code }}</span>
            </component>
        </div>

        <template v-if="otherSuppliers.length">
            <div class="mb-2 mt-4 text-xs text-gray-500">
                {{ ctrans("Also supplied by :count other suppliers", { count: otherSuppliers.length }) }}
            </div>
            <div :class="gridClass">
                <div v-for="other in otherSuppliers" :key="other.supplier_code + other.code"
                    class="flex items-center gap-2 rounded-md border border-dashed border-gray-200 px-2 py-1.5">
                    <button v-if="other.image" type="button" class="h-8 w-8 shrink-0 cursor-zoom-in overflow-hidden rounded" v-tooltip="ctrans('View image')"
                        @click="openImageViewer(other.image, other.supplier_name)">
                        <Image :src="other.image" imageCover class="h-full w-full" />
                    </button>
                    <Link v-else :href="route(other.supplier_route.name, other.supplier_route.parameters)" class="shrink-0"
                        v-tooltip="ctrans('Open supplier')">
                        <Icon :data="{ icon: 'fal fa-person-dolly' }" class="w-8 text-center text-lg text-gray-400" />
                    </Link>
                    <div class="min-w-0">
                        <Link :href="route(other.supplier_route.name, other.supplier_route.parameters)"
                            class="block truncate text-xs font-medium text-gray-600 hover:text-gray-900 hover:underline">
                            {{ other.supplier_name }}
                        </Link>
                        <div class="whitespace-nowrap text-xxs text-gray-400">
                            <Link v-if="other.route" :href="route(other.route.name, other.route.parameters)"
                                class="hover:text-gray-700 hover:underline" v-tooltip="ctrans('Open supplier product')">
                                {{ other.code }}
                            </Link>
                            <span v-else>{{ other.code }}</span>
                            <span v-if="!other.is_available" class="ml-1 text-red-600">· {{ ctrans("Unavailable") }}</span>
                        </div>
                        <span v-if="other.preferred_in?.length" :class="preferredBadgeClass" v-tooltip="preferredTooltip(other.code, other.preferred_in, other.supplier_name)">
                            <Icon :data="{ icon: 'fal fa-star' }" />
                            {{ preferredLabel(other.preferred_in) }}
                        </span>
                        <template v-if="setPreferred?.organisations.length && other.can_be_preferred">
                            <div class="mt-1">
                                <Button type="tertiary" size="xxs" :label="ctrans('Set as preferred')" :loading="savingId === other.id"
                                    aria-haspopup="true" aria-controls="provided-by-preferred-menu"
                                    @click="(event: Event) => openPreferredMenu(event, other.id, other.preferred_in ?? [])">
                                    <template #icon>
                                        <Icon :data="{ icon: 'fal fa-check' }" class="text-green-600" />
                                    </template>
                                </Button>
                            </div>
                            <div v-if="result?.id === other.id" class="mt-1 text-xs" :class="{ 'text-red-600': result.tone === 'error', 'text-amber-600': result.tone === 'skipped', 'text-green-700': result.tone === 'done' }">{{ result.message }}</div>
                        </template>
                    </div>
                    <span class="ml-auto whitespace-nowrap text-xxs tabular-nums text-gray-400">{{ other.supplier_code }}</span>
                </div>
            </div>
        </template>

        <Menu id="provided-by-preferred-menu" ref="preferredMenu" :model="preferredMenuItems" popup class="text-sm" />
        <ProductImageViewerDialog v-if="viewerImages.length" v-model:visible="isImageViewerVisible" :images="viewerImages" />
    </div>
</template>
