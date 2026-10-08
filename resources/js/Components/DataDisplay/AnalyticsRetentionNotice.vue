<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle } from "@fal"

library.add(faInfoCircle)

defineProps<{
    summary: string
    detail: string
}>()

const routeParams = route().params as Record<string, string>
const seoDashboardHref = routeParams.shop ? route("grp.org.shops.show.seo.dashboard", [routeParams.organisation, routeParams.shop]) : null
</script>

<template>
    <div role="note" class="mx-4 mb-5 mt-4 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
        <FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5 shrink-0 text-[--app-accent-strong]" fixed-width aria-hidden="true" />
        <p class="text-gray-700">
            <span class="font-medium text-gray-900">{{ summary }}</span>
            {{ detail }}
            <Link
                v-if="seoDashboardHref"
                :href="seoDashboardHref"
                class="font-medium text-[--app-accent-strong] underline underline-offset-2 hover:text-[--app-accent-deep] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[--app-accent]">
                {{ ctrans("See their daily totals on the SEO dashboard") }}
            </Link>
        </p>
    </div>
</template>
