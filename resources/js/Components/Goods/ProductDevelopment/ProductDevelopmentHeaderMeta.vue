<!--
  - Author Louis Perez
  - Created on 16-10-2026-00h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import ProductDevelopmentPurchaseOrders from "@/Components/Goods/ProductDevelopment/ProductDevelopmentPurchaseOrders.vue"
import ProductDevelopmentTargetDates from "@/Components/Goods/ProductDevelopment/ProductDevelopmentTargetDates.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faIndustry, faUserTie, faTags, faAtomAlt, faBoxOpen, faFlask } from "@fal"
library.add(faIndustry, faUserTie, faTags, faAtomAlt, faBoxOpen, faFlask)

interface LinkedTag {
    label: string
    name?: string
    color: string
    route: routeType
}

defineProps<{
    header: {
        kind: { label: string, icon: string, color: string }
        production: { label: string, organisation: string | null, color: string, route: routeType } | null
        type: { label: string, icon: string, color: string }
        status: { label: string, icon: string, color: string }
        brand: LinkedTag | null
        family: LinkedTag | null
        supplier: LinkedTag | null
        agent: LinkedTag | null
        owner: { id: number, name: string, username: string | null, avatar: Record<string, string> | null, profile_url: string | null } | null
        markets: string[]
        target_landing_date: string | null
        target_online_date: string | null
        can_edit: boolean
        update_route: routeType
        number_purchase_orders: number
        purchase_orders: InstanceType<typeof ProductDevelopmentPurchaseOrders>["$props"]["data"]
    }
}>()

const linkClass = (color: string) => [productDevelopmentColor(color).active, productDevelopmentColor(color).link]
</script>

<template>
    <div class="border-b border-gray-200 px-4 py-3 md:px-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(header.status.color).active">
                <FontAwesomeIcon :icon="header.status.icon" fixed-width aria-hidden="true" />
                {{ header.status.label }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(header.kind.color).active">
                <FontAwesomeIcon :icon="header.kind.icon" fixed-width aria-hidden="true" />
                {{ header.kind.label }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium" :class="productDevelopmentColor(header.type.color).active">
                <FontAwesomeIcon :icon="header.type.icon" fixed-width aria-hidden="true" />
                {{ header.type.label }}
            </span>
            <Link v-if="header.brand" :href="route(header.brand.route.name, header.brand.route.parameters)" class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition-colors" :class="linkClass(header.brand.color)" v-tooltip="ctrans('Brand')">
                <FontAwesomeIcon icon="fal fa-tags" fixed-width aria-hidden="true" />
                {{ header.brand.label }}
            </Link>
            <Link v-if="header.family" :href="route(header.family.route.name, header.family.route.parameters)" class="inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition-colors" :class="linkClass(header.family.color)" v-tooltip="header.family.name ? ctrans('Trade unit family') + ': ' + header.family.name : ctrans('Trade unit family')">
                <FontAwesomeIcon icon="fal fa-atom-alt" fixed-width aria-hidden="true" />
                {{ header.family.label }}
            </Link>
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-5">
            <div class="col-span-2 md:col-span-1">
                <dt class="text-xs text-gray-500">{{ header.production ? ctrans("Made by") : ctrans("Supplier / agent") }}</dt>
                <dd v-if="header.production" class="mt-1 flex flex-wrap items-center gap-1.5">
                    <Link :href="route(header.production.route.name, header.production.route.parameters)" class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition-colors" :class="linkClass(header.production.color)" v-tooltip="header.production.organisation">
                        <FontAwesomeIcon icon="fal fa-flask" fixed-width aria-hidden="true" />
                        <span class="truncate">{{ header.production.label }}</span>
                    </Link>
                </dd>
                <dd v-else class="mt-1 flex flex-wrap items-center gap-1.5">
                    <Link v-if="header.supplier" :href="route(header.supplier.route.name, header.supplier.route.parameters)" class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition-colors" :class="linkClass(header.supplier.color)">
                        <FontAwesomeIcon icon="fal fa-industry" fixed-width aria-hidden="true" />
                        <span class="truncate">{{ header.supplier.label }}</span>
                    </Link>
                    <span v-if="header.supplier && header.agent" class="text-xs text-gray-400">{{ ctrans("via") }}</span>
                    <Link v-if="header.agent" :href="route(header.agent.route.name, header.agent.route.parameters)" class="inline-flex max-w-full items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition-colors" :class="linkClass(header.agent.color)">
                        <FontAwesomeIcon icon="fal fa-user-tie" fixed-width aria-hidden="true" />
                        <span class="truncate">{{ header.agent.label }}</span>
                    </Link>
                    <span v-if="!header.supplier && !header.agent" class="text-gray-400">—</span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ ctrans("Markets") }}</dt>
                <dd class="mt-1 text-gray-800">{{ header.markets.length ? header.markets.join(", ") : "—" }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ ctrans("Target online") }}</dt>
                <dd class="mt-1">
                    <ProductDevelopmentTargetDates :landing="header.target_landing_date" :online="header.target_online_date" :canEdit="header.can_edit" :updateRoute="header.update_route" />
                </dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ ctrans("Purchase orders") }}</dt>
                <dd class="mt-1"><ProductDevelopmentPurchaseOrders :data="header.purchase_orders" /></dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ ctrans("Owner") }}</dt>
                <dd class="mt-1 flex items-center gap-2 text-gray-800">
                    <TicketUserHoverCard
                        v-if="header.owner"
                        :name="header.owner.name"
                        :avatar="header.owner.avatar"
                        :username="header.owner.username"
                        :profileUrl="header.owner.profile_url"
                        :messageUserId="header.owner.id"
                        :canMention="false"
                        size="sm"
                        class="font-normal"
                    />
                    <span v-else class="text-gray-400">—</span>
                </dd>
            </div>
        </dl>
    </div>
</template>
