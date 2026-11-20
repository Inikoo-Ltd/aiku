<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 20 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShieldCheck, faClock, faExclamationCircle } from "@fal"
library.add(faShieldCheck, faClock, faExclamationCircle)

const props = defineProps<{
    artwork: {
        compliance_ok: { by: string | null, at: string } | null
        can_compliance_ok: boolean
        compliance_ok_blocked: string | null
        compliance_ok_route: routeType
    }
}>()

const giving = ref(false)
const error = ref<string | null>(null)

function giveComplianceOk() {
    router.patch(route(props.artwork.compliance_ok_route.name, props.artwork.compliance_ok_route.parameters), {}, {
        preserveScroll: true,
        only: ["design"],
        onStart: () => { giving.value = true; error.value = null },
        onFinish: () => giving.value = false,
        onError: (errors) => error.value = Object.values(errors)[0] ?? null,
    })
}
</script>

<template>
    <div class="rounded-md border px-3 py-2 text-xs" :class="artwork.compliance_ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
        <p v-if="artwork.compliance_ok" class="flex items-center gap-2">
            <FontAwesomeIcon icon="fal fa-shield-check" class="shrink-0" fixed-width aria-hidden="true" />
            {{ ctrans("Compliance OK by :name on :date", { name: artwork.compliance_ok.by ?? "", date: useFormatTime(artwork.compliance_ok.at, { formatTime: "d MMM yyyy, HH:mm" }) }) }}
        </p>
        <div v-else-if="artwork.can_compliance_ok" class="space-y-2">
            <p v-if="artwork.compliance_ok_blocked" class="flex gap-2">
                <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                {{ artwork.compliance_ok_blocked }}
            </p>
            <p v-else>{{ ctrans("Check this version. Your OK lets the owner or an approver approve it.") }}</p>
            <p v-if="error" class="text-red-600">{{ error }}</p>
            <div class="flex justify-end">
                <Button type="tertiary" size="xs" :label="ctrans('Compliance OK')" :loading="giving" :disabled="!!artwork.compliance_ok_blocked" @click="giveComplianceOk">
                    <template #icon><FontAwesomeIcon icon="fal fa-shield-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </div>
        <p v-else class="flex items-center gap-2">
            <FontAwesomeIcon icon="fal fa-clock" class="shrink-0" fixed-width aria-hidden="true" />
            {{ ctrans("Waiting for the Compliance OK") }}
        </p>
    </div>
</template>
