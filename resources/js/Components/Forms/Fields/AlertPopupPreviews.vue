<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingCart, faLifeRing, faCommentAlt, faEye } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { previewAlertPopup, type AlertPopupPreview } from "@/Composables/useNotificationSound"
import { useLayoutStore } from "@/Stores/layout"

library.add(faShoppingCart, faLifeRing, faCommentAlt, faEye)

defineProps<{
    form: any
    fieldName: string
    options?: {}
    fieldData: {}
}>()

const layout = useLayoutStore()

const previews = computed<{ kind: AlertPopupPreview; label: string; icon: string; iconClass: string }[]>(() => [
    { kind: "order", label: ctrans("New order"), icon: "fal fa-shopping-cart", iconClass: "bg-emerald-100 text-emerald-600" },
    { kind: "unpaid_order", label: ctrans("Unpaid order"), icon: "fal fa-shopping-cart", iconClass: "bg-amber-100 text-amber-600" },
    { kind: "ticket", label: ctrans("Ticket done"), icon: "fal fa-life-ring", iconClass: "bg-emerald-100 text-emerald-600" },
    ...(layout.user?.is_agent ? [{ kind: "customer_message" as const, label: ctrans("Customer message"), icon: "fal fa-comment-alt", iconClass: "bg-red-100 text-red-600" }] : []),
])
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="preview in previews"
            :key="preview.kind"
            type="button"
            class="inline-flex items-center gap-x-2 rounded-lg border border-gray-200 bg-white py-1.5 pl-1.5 pr-3 text-sm text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50"
            @click="previewAlertPopup(preview.kind)">
            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="preview.iconClass">
                <FontAwesomeIcon :icon="preview.icon" fixed-width aria-hidden="true" />
            </span>
            {{ preview.label }}
            <FontAwesomeIcon icon="fal fa-eye" class="text-gray-400" fixed-width aria-hidden="true" />
        </button>
    </div>
</template>
