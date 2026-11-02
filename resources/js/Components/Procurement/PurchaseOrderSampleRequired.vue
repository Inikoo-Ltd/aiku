<!--
  - Author Louis Perez
  - Created on 27-10-2026-14h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import ToggleSwitch from "primevue/toggleswitch"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlask } from "@fal"
library.add(faFlask)

export interface PurchaseOrderSample {
    required: boolean
    approved: boolean
    override_reason: string | null
    route: routeType | null
}

const props = defineProps<{
    sample: PurchaseOrderSample
}>()

const saving = ref(false)

function toggle(required: boolean) {
    if (!props.sample.route) {
        return
    }
    router.patch(route(props.sample.route.name, props.sample.route.parameters), { sample_required: required }, {
        preserveScroll: true,
        only: ["sampleRequirement", "stages", "pageHead"],
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (errors) => notify({ title: ctrans("Something went wrong"), text: Object.values(errors).join(" "), type: "error" }),
    })
}
</script>

<template>
    <div class="flex items-center gap-3 text-sm">
        <FontAwesomeIcon v-tooltip="ctrans('Sample')" icon="fal fa-flask" class="text-gray-400" aria-hidden="true" fixed-width />
        <label class="flex items-center gap-2" :class="sample.route ? 'cursor-pointer' : ''">
            <ToggleSwitch :modelValue="sample.required" :disabled="!sample.route || saving" @update:modelValue="toggle" />
            <span class="text-gray-700">{{ ctrans("Sample must be approved before shipping") }}</span>
        </label>
        <span v-if="sample.required" class="text-xs" :class="sample.approved ? 'text-green-700' : 'text-amber-700'">
            {{ sample.approved ? ctrans("Approved") : sample.override_reason ? ctrans("Ships without it") : ctrans("Not approved yet") }}
        </span>
    </div>
</template>
