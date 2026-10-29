<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { onBeforeUnmount, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableDevelopmentRoom from "@/Components/Tables/Grp/Org/Production/TableDevelopmentRoom.vue"
import { StatsBoxTS } from "@/types/Components/StatsBox"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Navigation } from "@/types/Tabs"
import { routeType } from "@/types/route"
import { useTabChange, useCurrentTab } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVial, faFlask, faRocketLaunch, faBan } from "@fal"

library.add(faVial, faFlask, faRocketLaunch, faBan)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    stats: StatsBoxTS[]
    tabs: {
        current: string
        navigation: Navigation
    }
    routes: {
        artefact: routeType
        job_order: routeType
    }
    actions: {
        trial_run_route: routeType
        launch_route: routeType
        cancel_route: routeType
        artisans: { id: number, name: string }[]
    } | null
    experiments?: object
    trial_runs?: object
    launched?: object
    cancelled?: object
}>()

const currentTab = useCurrentTab(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const confettiDefaults = {
    spread: 360,
    ticks: 50,
    gravity: 0,
    decay: 0.94,
    startVelocity: 30,
    shapes: ["star"],
    zIndex: 100,
    disableForReducedMotion: false,
}

type ConfettiFn = typeof import("@tsparticles/confetti")["confetti"]

const getReducedMotionAdjustments = () => {
    const isReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches

    return {
        particleMultiplier: isReducedMotion ? 3 : 1,
        startVelocity: isReducedMotion ? 75 : confettiDefaults.startVelocity,
    }
}

const shootConfetti = (confetti: ConfettiFn) => {
    const { particleMultiplier, startVelocity } = getReducedMotionAdjustments()

    confetti("development-confetti", {
        ...confettiDefaults,
        startVelocity,
        particleCount: 40 * particleMultiplier,
        scalar: 1.2,
        shapes: ["star"],
    })

    confetti("development-confetti", {
        ...confettiDefaults,
        startVelocity,
        particleCount: 10 * particleMultiplier,
        scalar: 0.75,
        shapes: ["circle"],
    })
}

const shootMultipleConfetti = async () => {
    const { confetti } = await import("@tsparticles/confetti")

    setTimeout(() => {
        setTimeout(() => shootConfetti(confetti), 0)
        setTimeout(() => shootConfetti(confetti), 100)
        setTimeout(() => shootConfetti(confetti), 200)
        setTimeout(() => shootConfetti(confetti), 300)
    }, 300)
}

const launchedCode = ref<string | null>(null)
let launchedBannerTimer: ReturnType<typeof setTimeout> | null = null

const onLaunched = (code: string) => {
    launchedCode.value = code
    shootMultipleConfetti()

    if (launchedBannerTimer) {
        clearTimeout(launchedBannerTimer)
    }
    launchedBannerTimer = setTimeout(() => launchedCode.value = null, 6000)
}

onBeforeUnmount(() => {
    if (launchedBannerTimer) {
        clearTimeout(launchedBannerTimer)
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <dl class="mx-4 mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
        <StatsBox v-for="(stat, index) in stats" :key="index" :stat="stat" />
    </dl>

    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-y-1 opacity-0"
        leave-active-class="transition duration-500 ease-in"
        leave-to-class="opacity-0">
        <div v-if="launchedCode" class="mx-4 mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-medium text-green-800" role="status" aria-live="polite">
            🚀 {{ ctrans(":code launched", { code: launchedCode }) }}
        </div>
    </Transition>

    <Tabs :current="currentTab" :navigation="tabs.navigation" class="mt-4" @update:tab="handleTabUpdate" />
    <TableDevelopmentRoom
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab as 'experiments' | 'trial_runs' | 'launched' | 'cancelled'] ?? {}"
        :routes="routes"
        :actions="actions"
        @launched="onLaunched" />
</template>

<style>
#development-confetti {
    pointer-events: none;
}
</style>
