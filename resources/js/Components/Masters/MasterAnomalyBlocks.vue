<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faHockeyMask, faRaygun, faStarfighter, faStarfighterAlt } from "@fal"
import { faSpinnerThird } from "@fad"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

library.add(faCheck, faHockeyMask, faRaygun, faStarfighter, faStarfighterAlt, faSpinnerThird)

// Deviations from the master, split by intent: a product that should follow but
// does not is an anomaly (red, fixed from the master), one flagged as
// not_follow_master_* is a rebel (yellow, killed one by one).
const props = defineProps<{
    anomalies?: {
        items: {
            product_id: number
            shop_code: string
            shop_slug: string
            url: string
            issues: string[]
            ignored_issues: string[]
            ignored_scopes: string[]
        }[]
        fixRoute: routeType
        killRebelRoute: routeType
    } | null
}>()

const anomalyItems = computed(() => (props.anomalies?.items ?? []).filter(item => item.issues.length))
const rebelItems = computed(() => (props.anomalies?.items ?? []).filter(item => item.ignored_issues.length))

// The fan-out is queued for masters with more than a handful of products, so the
// request returns before the work is done: CascadeMasterAssetPricesToChildren and
// FixProductTradeUnitsFromMaster broadcast their progress on the master's channel.
const cascadeProgress = ref<{ state: string; type?: string; done: number; total: number } | null>(null)

const onCascadeProgress = (event: { state: string; type?: string; done: number; total: number }) => {
    cascadeProgress.value = event
    if (event.state === "done") {
        router.reload({ preserveScroll: true })
        setTimeout(() => (cascadeProgress.value = null), 4000)
    }
}

const masterAssetId = computed(() => props.anomalies?.fixRoute?.parameters?.masterAsset)

onMounted(() => {
    if (window.Echo && masterAssetId.value) {
        window.Echo.private(`grp.master-asset.${masterAssetId.value}`)
            .listen(".prices-cascade-progress", onCascadeProgress)
    }
})

onUnmounted(() => {
    if (window.Echo && masterAssetId.value) {
        window.Echo.private(`grp.master-asset.${masterAssetId.value}`)
            .stopListening(".prices-cascade-progress", onCascadeProgress)
    }
})

const confirm = useConfirm()
const isFixingAnomalies = ref(false)
const killingRebelId = ref<number | null>(null)

const onKillRebel = (event: MouseEvent, item: { product_id: number; shop_code: string; ignored_scopes: string[] }) => {
    if (!props.anomalies) return
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: item.ignored_scopes.length === 1 && item.ignored_scopes[0] === 'trade_units'
            ? trans("Kill this rebel? :shop will follow the master's composition and picking again and be fixed from it. Its price settings stay untouched.", { shop: item.shop_code })
            : item.ignored_scopes.length === 1 && item.ignored_scopes[0] === 'prices'
                ? trans("Kill this price rebel? :shop will follow the master prices again and be fixed from them. Its composition settings stay untouched.", { shop: item.shop_code })
                : trans("Kill this rebel? :shop will follow the master again and its composition, picking and prices will be fixed from the master.", { shop: item.shop_code }),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("Kill rebel"),
        rejectLabel: trans("Cancel"),
        acceptClass: "p-button-danger",
        rejectClass: "p-button-text",
        accept: () => {
            router.post(
                route(props.anomalies!.killRebelRoute.name, { ...props.anomalies!.killRebelRoute.parameters, product: item.product_id }),
                { scope: item.ignored_scopes.length === 1 ? item.ignored_scopes[0] : 'all' },  // both flags set → 'all'
                {
                    preserveScroll: true,
                    onStart: () => (killingRebelId.value = item.product_id),
                    onFinish: () => (killingRebelId.value = null),
                }
            )
        },
    })
}

const onFixAnomalies = (event: MouseEvent) => {
    if (!props.anomalies) return
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: trans("This will copy the master's composition, picking and prices to every shop product that follows the master. Continue?"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("Fix anomalies"),
        rejectLabel: trans("Cancel"),
        acceptClass: "p-button-danger",
        rejectClass: "p-button-text",
        accept: () => {
            router.post(
                route(props.anomalies!.fixRoute.name, props.anomalies!.fixRoute.parameters),
                {},
                {
                    preserveScroll: true,
                    onStart: () => (isFixingAnomalies.value = true),
                    onFinish: () => (isFixingAnomalies.value = false),
                }
            )
        },
    })
}
</script>

<template>
    <div v-if="anomalyItems.length || rebelItems.length || cascadeProgress" class="flex flex-col gap-y-4">
        <ConfirmPopup />

        <div v-if="cascadeProgress"
            class="flex items-center gap-x-2 rounded-lg border px-4 py-2.5 text-sm"
            :class="cascadeProgress.state === 'done' ? 'border-green-200 bg-green-50 text-green-700' : 'border-sky-200 bg-sky-50 text-sky-700'">
            <FontAwesomeIcon v-if="cascadeProgress.state !== 'done'" icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fal fa-check" fixed-width aria-hidden="true" />
            <span v-if="cascadeProgress.state === 'done'">
                {{ trans("Shop products updated") }} ({{ cascadeProgress.total }})
            </span>
            <span v-else>
                {{ trans("Updating shop products") }}… {{ cascadeProgress.done }}/{{ cascadeProgress.total }}
            </span>
        </div>
        <section v-if="anomalyItems.length" class="rounded-lg border border-red-300 bg-red-50">
            <div class="border-b border-red-200 px-4 py-3 sm:px-6 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <FontAwesomeIcon icon="fal fa-hockey-mask" class="text-red-500" fixed-width aria-hidden="true" />
                    <h2 class="font-medium text-red-800">
                        {{ trans("Anomalies detected") }} ({{ anomalyItems.length }})
                    </h2>
                </div>
                <Button
                    type="negative"
                    icon="fal fa-raygun"
                    :label="trans('Fix anomalies from master')"
                    v-tooltip="trans('Copy the master\'s composition, picking and prices to every shop product that follows it')"
                    :loading="isFixingAnomalies"
                    size="xs"
                    @click="onFixAnomalies($event)"
                />
            </div>
            <ul class="px-4 py-3 sm:px-6 flex flex-col gap-y-1.5 text-sm text-red-900">
                <li v-for="item in anomalyItems" :key="item.product_id" class="flex items-start gap-x-2">
                    <a :href="item.url" target="_blank" class="font-medium uppercase shrink-0 w-12 underline decoration-dotted hover:text-red-700">{{ item.shop_code }}</a>
                    <span class="flex-1">{{ item.issues.join("; ") }}</span>
                </li>
            </ul>
        </section>

        <section v-if="rebelItems.length" class="rounded-lg border border-amber-300 bg-amber-50">
            <div class="border-b border-amber-200 px-4 py-3 sm:px-6 flex items-center gap-2">
                <FontAwesomeIcon icon="fal fa-starfighter" class="text-yellow-500" fixed-width aria-hidden="true" />
                <h2 class="font-medium text-amber-800">
                    {{ trans("Rebels") }} ({{ rebelItems.length }})
                    <span class="font-normal text-amber-800/70 text-sm">— {{ trans("deliberately not following master") }}</span>
                </h2>
            </div>
            <ul class="px-4 py-3 sm:px-6 flex flex-col gap-y-1.5 text-sm text-amber-900">
                <li v-for="item in rebelItems" :key="item.product_id" class="flex items-start gap-x-2">
                    <a :href="item.url" target="_blank" class="font-medium uppercase shrink-0 w-12 underline decoration-dotted hover:text-amber-700">{{ item.shop_code }}</a>
                    <span class="flex-1 italic">{{ item.ignored_issues.join("; ") }}</span>
                    <button
                        v-if="item.ignored_scopes.length"
                        type="button"
                        class="flex shrink-0 items-center gap-x-1.5 rounded-md border border-amber-300 bg-white px-2 py-1 text-xs font-medium text-amber-600 hover:bg-amber-100 hover:text-amber-700 disabled:cursor-not-allowed disabled:border-gray-200 disabled:text-gray-300"
                        v-tooltip="trans('Kill rebel: make it follow the master and autofix')"
                        :disabled="killingRebelId === item.product_id"
                        @click="onKillRebel($event, item)"
                    >
                        <FontAwesomeIcon
                            v-if="killingRebelId === item.product_id"
                            icon="fad fa-spinner-third"
                            class="animate-spin"
                            fixed-width
                            aria-hidden="true"
                        />
                        <FontAwesomeIcon v-else icon="fal fa-starfighter-alt" fixed-width aria-hidden="true" />
                        {{ trans("Kill rebel") }}
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
