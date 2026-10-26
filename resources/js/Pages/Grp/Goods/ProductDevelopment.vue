<!--
  - Author Louis Perez
  - Created on 15-10-2026-19h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import ProductDevelopmentTabs from "@/Components/Goods/ProductDevelopment/ProductDevelopmentTabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import type { Navigation } from "@/types/Tabs"
import ProductDevelopmentActivity from "@/Components/Goods/ProductDevelopment/ProductDevelopmentActivity.vue"
import ProductDevelopmentActivityOrder from "@/Components/Goods/ProductDevelopment/ProductDevelopmentActivityOrder.vue"
import ProductDevelopmentSidePanel from "@/Components/Goods/ProductDevelopment/ProductDevelopmentSidePanel.vue"
import ProductDevelopmentHeaderMeta from "@/Components/Goods/ProductDevelopment/ProductDevelopmentHeaderMeta.vue"
import ProductDevelopmentStageStrip from "@/Components/Goods/ProductDevelopment/ProductDevelopmentStageStrip.vue"
import ProductDevelopmentRoleCards from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRoleCards.vue"
import ProductDevelopmentFinalApproval from "@/Components/Goods/ProductDevelopment/ProductDevelopmentFinalApproval.vue"
import ProductDevelopmentWaitingOn from "@/Components/Goods/ProductDevelopment/ProductDevelopmentWaitingOn.vue"
import ProductDevelopmentPushPrompt from "@/Components/Goods/ProductDevelopment/ProductDevelopmentPushPrompt.vue"
import ProductDevelopmentBrief from "@/Components/Goods/ProductDevelopment/ProductDevelopmentBrief.vue"
import ProductDevelopmentProducts from "@/Components/Goods/ProductDevelopment/ProductDevelopmentProducts.vue"
import ProductDevelopmentCompliance from "@/Components/Goods/ProductDevelopment/ProductDevelopmentCompliance.vue"
import ProductDevelopmentComments from "@/Components/Goods/ProductDevelopment/ProductDevelopmentComments.vue"
import ProductDevelopmentTasks from "@/Components/Goods/ProductDevelopment/ProductDevelopmentTasks.vue"
import ProductDevelopmentFiles from "@/Components/Goods/ProductDevelopment/ProductDevelopmentFiles.vue"
import ProductDevelopmentDesign from "@/Components/Goods/ProductDevelopment/ProductDevelopmentDesign.vue"
import ProductDevelopmentFormulation from "@/Components/Goods/ProductDevelopment/ProductDevelopmentFormulation.vue"
import ProductDevelopmentBuyersList from "@/Components/Goods/ProductDevelopment/ProductDevelopmentBuyersList.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlayCircle, faPauseCircle, faCheckCircle, faBan, faSparkles, faPaintBrush, faTag, faLightbulbOn, faClipboard, faAtom, faShieldCheck, faPencilRuler, faPaperclip, faTasks, faClock, faLock, faArrowRight } from "@fal"
library.add(faPlayCircle, faPauseCircle, faCheckCircle, faBan, faSparkles, faPaintBrush, faTag, faLightbulbOn, faClipboard, faAtom, faShieldCheck, faPencilRuler, faPaperclip, faTasks, faClock, faLock, faArrowRight)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    header: any
    overview: {
        strip: any[]
        roles: any[]
        products: any[]
        waiting_on: any[]
    }
    stageMove: {
        can_move: boolean
        reason: string | null
        is_allowed: boolean
        is_done: boolean
        next_label: string
        route: routeType & { method: string }
    }
    tabs: {
        current: string
        navigation: Navigation
    }
    brief?: object
    products?: object
    formulation?: object
    buyers_list?: object
    compliance?: object
    design?: object
    activity?: object
    files?: object
    tasks?: object
    comments: any
    recentActivity: any[]
    push?: { public_key: string, profile_route: routeType } | null
    reminders: { route: routeType, last_sent: { at: string, by: string, roles: string[] } | null } | null
    finalApproval: InstanceType<typeof ProductDevelopmentFinalApproval>["$props"]["data"] | null
    editor: any
    canEditStageDates: boolean
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const JUMP_WAIT_MS = 8000
const JUMP_HIGHLIGHT = ["rounded-md", "ring-2", "ring-[--app-accent]", "ring-offset-4"]

/**
 * From "Waiting on": opens the tab the field lives on, waits for it to load, then brings the field into
 * view and outlines it for a moment. The field's box takes focus, not its input, so no phone keyboard opens.
 */
function jumpTo({ tab, target }: { tab: string, target: string }) {
    if (currentTab.value !== tab) {
        handleTabUpdate(tab)
    }
    const startedAt = Date.now()
    const reveal = () => {
        const element = document.getElementById(target)
        if (!element || element.offsetParent === null) {
            if (Date.now() - startedAt < JUMP_WAIT_MS) {
                requestAnimationFrame(reveal)
            }
            return
        }
        element.scrollIntoView({ behavior: "smooth", block: "center" })
        element.focus({ preventScroll: true })
        element.classList.add(...JUMP_HIGHLIGHT)
        setTimeout(() => element.classList.remove(...JUMP_HIGHLIGHT), 2000)
    }
    requestAnimationFrame(reveal)
}

/** A link from a reminder can name the field to bring into view, e.g. ?tab=products&focus=pd-check-12 */
onMounted(() => {
    const focus = new URLSearchParams(window.location.search).get("focus")
    if (focus && /^pd-check-\d+$/.test(focus)) {
        jumpTo({ tab: currentTab.value, target: focus })
    }
})

const component = computed(() => {
    const components: Record<string, any> = {
        brief: ProductDevelopmentBrief,
        products: ProductDevelopmentProducts,
        formulation: ProductDevelopmentFormulation,
        buyers_list: ProductDevelopmentBuyersList,
        compliance: ProductDevelopmentCompliance,
        design: ProductDevelopmentDesign,
        files: ProductDevelopmentFiles,
        tasks: ProductDevelopmentTasks,
        activity: ProductDevelopmentActivity,
    }

    return components[currentTab.value]
})

const moving = ref(false)
const recentOrder = ref<"newest" | "oldest">("newest")
const commentOrder = ref<"newest" | "oldest">("newest")

function moveToNextStage() {
    router.patch(route(props.stageMove.route.name, props.stageMove.route.parameters), {}, {
        preserveScroll: true,
        onStart: () => moving.value = true,
        onFinish: () => moving.value = false,
        onError: (errors) => notify({ title: ctrans("Stage not moved"), text: Object.values(errors).join(" "), type: "error" }),
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="!stageMove.is_done && stageMove.is_allowed" class="flex flex-col items-end gap-1">
                <Button
                    type="primary"
                    :icon="stageMove.can_move ? 'fal fa-arrow-right' : 'fal fa-lock'"
                    :label="ctrans('Move to :stage', { stage: stageMove.next_label })"
                    :disabled="!stageMove.can_move"
                    :loading="moving"
                    :tooltip="!stageMove.can_move ? stageMove.reason ?? undefined : undefined"
                    class="disabled:cursor-not-allowed disabled:opacity-50"
                    @click="moveToNextStage"
                />
                <span v-if="!stageMove.can_move && stageMove.reason" class="text-xs text-amber-700">{{ stageMove.reason }}</span>
            </div>
        </template>
    </PageHeading>

    <ProductDevelopmentHeaderMeta :header="header" />
    <ProductDevelopmentStageStrip :segments="overview.strip" :canEditDates="canEditStageDates" />
    <ProductDevelopmentRoleCards :roles="overview.roles" />

    <div class="grid gap-4 px-4 pb-6 md:px-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-3">
            <ProductDevelopmentTabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
            <div :class="['products', 'compliance', 'brief', 'design'].includes(currentTab) ? '' : 'overflow-hidden rounded-lg border border-gray-200 bg-white'">
                <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" v-bind="currentTab === 'brief' ? { editor } : {}" />
            </div>
        </div>

        <aside class="space-y-4">
            <ProductDevelopmentPushPrompt v-if="push" :push="push" />
            <ProductDevelopmentFinalApproval v-if="finalApproval" :data="finalApproval" />
            <ProductDevelopmentWaitingOn :rows="overview.waiting_on" :reminders="reminders" @jump="jumpTo" />
            <ProductDevelopmentSidePanel :title="ctrans('Comments')">
                <template #actions>
                    <ProductDevelopmentActivityOrder v-model="commentOrder" />
                </template>
                <ProductDevelopmentComments :data="comments" :order="commentOrder" />
            </ProductDevelopmentSidePanel>
            <ProductDevelopmentSidePanel :title="ctrans('Recent activity')">
                <template #actions>
                    <ProductDevelopmentActivityOrder v-model="recentOrder" />
                </template>
                <div class="max-h-[28rem] overflow-y-auto [scrollbar-width:thin]">
                    <ProductDevelopmentActivity :data="recentActivity" :order="recentOrder" compact />
                </div>
            </ProductDevelopmentSidePanel>
        </aside>
    </div>
</template>
