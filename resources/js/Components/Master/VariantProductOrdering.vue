<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar } from "@fas"
import { faShapes } from "@fal"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"
import { notify } from "@kyvg/vue3-notification"
import { ToggleSwitch } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue"
import { ctrans } from "@/Composables/useTrans"

type OrderedProduct = {
    id: number
    code: string
    name: string | null
    image_thumbnail?: any
    url?: string
    unit?: string | null
    option_label?: string | null
    used_in?: number
    is_variant_leader?: boolean
    status_icon?: { icon: string; class?: string; tooltip?: string }
}

const props = withDefaults(defineProps<{
    data: OrderedProduct[]
    reorderRoute: { name: string; parameters: Record<string, unknown> } | null
    followsMaster?: boolean | null
}>(), {
    followsMaster: null,
})

const products = ref<OrderedProduct[]>([...(props.data ?? [])])
const isDirty = ref(false)
const isSaving = ref(false)
const isFollowingMaster = ref(props.followsMaster === true)
const canReorder = computed(() => !!props.reorderRoute && !isFollowingMaster.value)

watch(() => props.data, (data) => {
    products.value = [...(data ?? [])]
    isDirty.value = false
})

watch(() => props.followsMaster, (follows) => (isFollowingMaster.value = follows === true))

const onFollowToggle = (follows: boolean) => {
    if (follows) {
        followMaster()
    } else {
        isFollowingMaster.value = false
    }
}

const onReorder = (reordered: OrderedProduct[]) => {
    products.value = reordered
    isDirty.value = true
}

const save = (payload: Record<string, unknown>, successText: string) => {
    if (!props.reorderRoute) return
    router.patch(route(props.reorderRoute.name, props.reorderRoute.parameters), payload, {
        preserveScroll: true,
        onStart: () => (isSaving.value = true),
        onSuccess: () => {
            isDirty.value = false
            notify({ title: ctrans("Success!"), text: successText, type: "success" })
        },
        onError: (errors: Record<string, string>) =>
            notify({ title: ctrans("Something went wrong"), text: Object.values(errors)[0] ?? ctrans("Failed to reorder products"), type: "error" }),
        onFinish: () => (isSaving.value = false),
    })
}

const saveOrder = () =>
    save({ products: products.value.map((product, index) => ({ id: product.id, index })) }, ctrans("Successfully reordered the products"))

const followMaster = () => save({ follow_master_variant_order: true }, ctrans("This variant follows the master order again"))
</script>

<template>
    <div>
        <div v-if="followsMaster !== null" class="mx-4 mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm">
            <ToggleSwitch :modelValue="isFollowingMaster" :disabled="!reorderRoute || isSaving" @update:modelValue="onFollowToggle" />
            <span class="text-gray-700">{{ ctrans("Follow the master variant's order") }}</span>
            <span class="text-xs text-gray-500">
                <template v-if="isFollowingMaster">{{ ctrans("This shop uses the master's order. Switch off to give it its own order.") }}</template>
                <template v-else-if="followsMaster">{{ ctrans("Reorder and save to give this shop its own order, or switch back on to keep the master's.") }}</template>
                <template v-else>{{ ctrans("This shop has its own order. Switch on to use the master's order again.") }}</template>
            </span>
        </div>

        <SetOrderingPositionOfProduct
            :data="products"
            :disabled="!canReorder"
            :allowPaste="false"
            @update:data="onReorder">
            <template #list-content="{ item }">
                <Image :src="item.image_thumbnail" class="h-10 w-10 shrink-0 rounded object-cover" />
                <Icon v-if="item.status_icon" :data="item.status_icon" class="shrink-0" />
                <FontAwesomeIcon
                    v-if="item.is_variant_leader !== undefined"
                    :icon="item.is_variant_leader ? faStar : faShapes"
                    v-tooltip="item.is_variant_leader ? ctrans('Leader') : ctrans('Option')"
                    class="shrink-0"
                    :class="item.is_variant_leader ? 'text-yellow-500' : 'text-gray-400'"
                    fixed-width />
                <div class="min-w-0 flex-1">
                    <Link v-if="item.url" :href="item.url" class="secondaryLink text-sm font-medium">{{ item.code }}</Link>
                    <div v-else class="text-sm font-medium">{{ item.code }}</div>
                    <div class="truncate text-xs text-gray-400">{{ item.name }}</div>
                </div>
                <span v-if="item.option_label" class="shrink-0 rounded-full border border-[--app-accent] bg-[--app-accent-soft] px-2.5 py-0.5 text-xs font-medium text-[--app-accent-strong]">
                    {{ item.option_label }}
                </span>
                <span v-if="item.unit" class="hidden shrink-0 text-xs text-gray-500 sm:inline">{{ item.unit }}</span>
                <span v-if="item.used_in !== undefined" v-tooltip="ctrans('Current products with this master')" class="w-16 shrink-0 text-right text-xs tabular-nums text-gray-500">
                    {{ ctrans("Used in :count", { count: item.used_in }) }}
                </span>
            </template>
            <template #card-content="{ item }">
                <Image :src="item.image_thumbnail" class="mb-2 h-24 w-full rounded object-cover" />
                <div class="flex items-center gap-1">
                    <Icon v-if="item.status_icon" :data="item.status_icon" />
                    <FontAwesomeIcon v-if="item.is_variant_leader !== undefined" :icon="item.is_variant_leader ? faStar : faShapes" :class="item.is_variant_leader ? 'text-yellow-500' : 'text-gray-400'" fixed-width />
                    <Link v-if="item.url" :href="item.url" class="secondaryLink text-xs">{{ item.code }}</Link>
                    <span v-else class="text-xs text-gray-400">{{ item.code }}</span>
                </div>
                <div class="line-clamp-2 text-sm font-medium">{{ item.name }}</div>
                <div v-if="item.option_label" class="mt-1 text-xs font-medium text-[--app-accent-strong]">{{ item.option_label }}</div>
            </template>
            <template #before-button-list>
                <Button
                    v-if="canReorder"
                    type="primary"
                    size="xs"
                    :label="ctrans('Save Order')"
                    :disabled="!isDirty"
                    :loading="isSaving"
                    @click="saveOrder" />
            </template>
        </SetOrderingPositionOfProduct>
    </div>
</template>
