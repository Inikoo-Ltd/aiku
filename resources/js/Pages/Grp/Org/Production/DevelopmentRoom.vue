<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { onBeforeUnmount, ref } from "vue"
import axios from "axios"
import AutoComplete from "primevue/autocomplete"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
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
import { faVial, faFlask, faRocketLaunch, faBan, faCodeBranch, faCodeMerge, faCheck, faTimes } from "@fal"

library.add(faVial, faFlask, faRocketLaunch, faBan, faCodeBranch, faCodeMerge, faCheck, faTimes)

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
        branch_route: routeType
        merge_route: routeType
        launched_search_route: routeType
        artisans: { id: number, name: string }[]
    } | null
    experiments?: object
    trial_runs?: object
    launched?: object
    cancelled?: object
    merged?: object
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

type LaunchedArtefact = { id: number, code: string, name: string | null }

const isBranchOpen = ref(false)
const isBranching = ref(false)
const branchOf = ref<LaunchedArtefact | null>(null)
const branchSuggestions = ref<LaunchedArtefact[]>([])
const branchError = ref<string | null>(null)

const openBranch = () => {
    branchOf.value = null
    branchError.value = null
    isBranchOpen.value = true
}

const searchLaunched = async (event: { query: string }) => {
    if (!props.actions) return

    try {
        const response = await axios.get(route(props.actions.launched_search_route.name, props.actions.launched_search_route.parameters), {
            params: { "filter[global]": event.query, perPage: 20 },
        })
        branchSuggestions.value = response.data.data
    } catch {
        branchSuggestions.value = []
        branchError.value = ctrans("Could not search the artefacts, try again")
    }
}

const submitBranch = () => {
    if (!props.actions || !branchOf.value) return

    router.post(
        route(props.actions.branch_route.name, { ...props.actions.branch_route.parameters, artefact: branchOf.value.id }),
        {},
        {
            onStart: () => isBranching.value = true,
            onFinish: () => isBranching.value = false,
            onError: (errors) => branchError.value = Object.values(errors).join(" "),
        }
    )
}

onBeforeUnmount(() => {
    if (launchedBannerTimer) {
        clearTimeout(launchedBannerTimer)
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <Button v-if="actions" type="tertiary" :label="ctrans('Branch')" v-tooltip="ctrans('Redesign a launched artefact as an experiment with a copy of its recipe')" @click="openBranch">
                <template #icon><FontAwesomeIcon :icon="['fal', 'code-branch']" fixed-width class="text-gray-500" aria-hidden="true" /></template>
            </Button>
        </template>
    </PageHeading>

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
        :data="props[currentTab as 'experiments' | 'trial_runs' | 'launched' | 'cancelled' | 'merged'] ?? {}"
        :routes="routes"
        :actions="actions"
        @launched="onLaunched" />

    <Dialog :visible="isBranchOpen" modal :closable="!isBranching" :header="ctrans('Branch for development')" :style="{ width: '28rem' }" @update:visible="(visible: boolean) => { if (!visible && !isBranching) isBranchOpen = false }">
        <div class="text-xs text-gray-500">{{ ctrans('An experiment with a copy of its recipe, batch size, shelf life and family. The artefact is made, ordered and sold as it is until the branch is merged back.') }}</div>

        <label for="branchOf" class="mt-4 block text-sm font-medium">{{ ctrans('Launched artefact') }}</label>
        <AutoComplete v-model="branchOf" inputId="branchOf" :suggestions="branchSuggestions" optionLabel="code" forceSelection :placeholder="ctrans('Search by code or name')" fluid class="mt-1" @complete="searchLaunched">
            <template #option="{ option }">
                <span class="whitespace-nowrap font-medium">{{ option.code }}</span>
                <span class="ml-2 text-gray-500">{{ option.name }}</span>
            </template>
        </AutoComplete>

        <div v-if="branchError" class="mt-3 text-xs text-red-600">{{ branchError }}</div>

        <div class="mt-5 flex justify-end gap-2">
            <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isBranching" @click="isBranchOpen = false">
                <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
            </Button>
            <Button type="tertiary" :label="ctrans('Branch')" :loading="isBranching" :disabled="!branchOf?.id" @click="submitBranch">
                <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width class="text-green-600" aria-hidden="true" /></template>
            </Button>
        </div>
    </Dialog>
</template>

<style>
#development-confetti {
    pointer-events: none;
}
</style>
