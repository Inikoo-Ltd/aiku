<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Menu from "primevue/menu"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faChevronDown, faBuilding } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    action: {
        key: string
        button: {
            organisation_name: string
            tooltip?: string
            route: routeType & { method?: string }
        }[]
    }
}>()

const menu = ref<InstanceType<typeof Menu> | null>(null)
const isCreating = ref(false)
const menuId = computed(() => `purchase-order-for-organisation-${props.action.key}`)

const createPurchaseOrder = (purchaseOrderRoute: routeType & { method?: string }) => {
    router.visit(route(purchaseOrderRoute.name, purchaseOrderRoute.parameters), {
        method: (purchaseOrderRoute.method ?? "post") as "post",
        onStart: () => (isCreating.value = true),
        onFinish: () => (isCreating.value = false),
    })
}

const items = computed(() =>
    props.action.button.map((button) => ({
        label: ctrans("For :organisation", { organisation: button.organisation_name }),
        command: () => createPurchaseOrder(button.route),
    }))
)
</script>

<template>
    <Button type="create" :label="ctrans('Purchase Order')" :icon="faPlus" :iconRight="faChevronDown" :loading="isCreating" aria-haspopup="true" :aria-controls="menuId" @click="(event) => menu?.toggle(event)" />
    <Menu :id="menuId" ref="menu" :model="items" popup class="text-sm">
        <template #item="{ item, props: itemProps }">
            <a v-bind="itemProps.action" class="flex items-center gap-2 whitespace-nowrap">
                <FontAwesomeIcon :icon="faBuilding" class="text-gray-500" fixed-width aria-hidden="true" />
                <span>{{ item.label }}</span>
            </a>
        </template>
    </Menu>
</template>
