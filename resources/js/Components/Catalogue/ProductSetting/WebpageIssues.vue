<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faExclamationTriangle, faInfoCircle } from "@fal"
import { faSync } from "@far"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import type { ShopProductSetting, WebpageIssueGroup } from "@/Components/Catalogue/ProductSetting/types"
import type { routeType } from "@/types/route"

const props = defineProps<{
    audit: NonNullable<ShopProductSetting["audit"]>
    crawlRoute: routeType | null
}>()

const severityStyle: Record<WebpageIssueGroup["issues"][number]["severity"], { label: string, icon: typeof faTimes, badge: string, border: string }> = {
    error: { label: ctrans("Error"), icon: faTimes, badge: "bg-red-50 text-red-700", border: "border-l-red-600" },
    warning: { label: ctrans("Warning"), icon: faExclamationTriangle, badge: "bg-amber-50 text-amber-700", border: "border-l-amber-500" },
    notice: { label: ctrans("Notice"), icon: faInfoCircle, badge: "bg-gray-100 text-gray-600", border: "border-l-gray-400" },
}

const isCrawling = ref(false)

const startCrawl = () => {
    if (!props.crawlRoute) {
        return
    }

    router.post(route(props.crawlRoute.name, props.crawlRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => (isCrawling.value = true),
        onSuccess: () => notify({ title: ctrans("Page checked"), text: ctrans("The checks show what the page has now"), type: "success" }),
        onError: (errors) => notify({ title: ctrans("Page not checked"), text: Object.values(errors)[0] ?? ctrans("The page could not be checked"), type: "error" }),
        onFinish: () => (isCrawling.value = false),
    })
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="audit.last_crawl_at && !audit.issue_groups.length"
            class="rounded-lg border border-green-200 bg-green-50 px-4 py-6 text-center text-sm font-semibold text-green-700"
        >
            {{ ctrans("The last crawl found no issues on this page") }}
        </div>

        <section v-for="group in audit.issue_groups" :key="group.key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-md">
            <h3 class="text-base font-semibold text-gray-900">
                {{ group.label }} <span class="font-normal text-gray-500">({{ group.issues.length }})</span>
            </h3>
            <p class="mb-3 text-xs text-gray-500">{{ group.intro }}</p>

            <div class="space-y-3">
                <div
                    v-for="issue in group.issues"
                    :key="issue.id"
                    class="rounded-md border border-l-4 border-gray-200 p-3"
                    :class="severityStyle[issue.severity].border"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="font-semibold text-gray-900">{{ issue.title }}</div>
                        <span class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold" :class="severityStyle[issue.severity].badge">
                            <FontAwesomeIcon :icon="severityStyle[issue.severity].icon" fixed-width aria-hidden="true" />
                            {{ severityStyle[issue.severity].label }}
                        </span>
                    </div>
                    <p v-if="issue.description" class="mt-1 text-xs text-gray-500">{{ issue.description }}</p>
                    <template v-if="issue.places?.length">
                        <div class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Where on the page") }}</div>
                        <ul class="mt-1 space-y-0.5 text-xs text-gray-700">
                            <li v-for="place in issue.places" :key="place">{{ place }}</li>
                        </ul>
                    </template>
                    <template v-if="issue.found">
                        <div class="mt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("What we found") }}</div>
                        <div class="mt-1 break-all rounded bg-gray-50 px-3 py-2 font-mono text-xs text-gray-700">{{ issue.found }}</div>
                    </template>
                </div>
            </div>
        </section>
    </div>
</template>
