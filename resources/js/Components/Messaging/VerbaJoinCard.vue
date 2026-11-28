<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { onMounted, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fal"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import Button from "@/Components/Elements/Buttons/Button.vue"
import VerbaArt from "@/Components/Messaging/VerbaArt.vue"
import SlackConsentDetails from "@/Components/Forms/Fields/SlackConsentDetails.vue"
import { ctrans } from "@/Composables/useTrans"
import { leaveVerbaJoin, recordVerbaJoin, type VerbaSlackJoin } from "@/Composables/useVerbaJoin"

const props = defineProps<{
    join: VerbaSlackJoin
}>()

const showDetails = ref(false)
const isBusy = ref(false)
const error = ref<string | null>(null)
const linkTicket = ref<string | null>(null)

onMounted(() => recordVerbaJoin("shown"))
watch(showDetails, (isOpen) => isOpen && recordVerbaJoin("policy_opened"))

const failed = () => error.value = ctrans("That did not save. Please try again")

const later = () => {
    recordVerbaJoin("later")
    leaveVerbaJoin()
}

const agreeAndConnect = () => {
    if (props.join.consent_version === "preview") {
        error.value = "Preview only: Slack cannot connect from a local machine."
        return
    }
    router.post(route(props.join.start_route.name), {
        understood: true,
        back_to_verba: true,
        consent_version: props.join.consent_version,
        terms_version: props.join.terms_version,
    }, {
        preserveScroll: true,
        onStart: () => { isBusy.value = true; error.value = null },
        onError: (errors) => error.value = Object.values(errors)[0] as string,
        onFinish: () => isBusy.value = false,
    })
}

const askHelpDeskToLink = () => {
    isBusy.value = true
    error.value = null
    axios.post(route("grp.chat.staff.slack.connect.ask-link"))
        .then(({ data }) => linkTicket.value = data.reference)
        .catch(failed)
        .finally(() => isBusy.value = false)
}

const startWithoutSlack = () => {
    isBusy.value = true
    error.value = null
    axios.patch(route("grp.models.profile.update"), { verba_no_slack: true })
        .then(() => router.visit(route("grp.chat.staff.feed")))
        .catch(failed)
        .finally(() => isBusy.value = false)
}
</script>

<template>
    <div class="flex w-full items-start justify-center overflow-y-auto bg-gray-50 px-4 py-16">
        <div class="w-full max-w-md space-y-4 rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-700">
            <h2 class="text-center font-[Lobster] text-3xl leading-none text-gray-800">{{ ctrans("Welcome to Verba") }}</h2>
            <VerbaArt class="mx-auto h-24 w-60" />
            <template v-if="join.linked">
                <p class="flex items-center justify-center gap-x-1.5 font-semibold text-gray-800">
                    <FontAwesomeIcon :icon="faSlack" class="text-gray-500" fixed-width aria-hidden="true" />
                    {{ ctrans("Connect your Slack") }}
                </p>
                <p>{{ ctrans("Your Slack messages come to Verba, and only the people in each conversation can see them.") }}</p>
                <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
                <div class="flex gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Not now')" @click="later" />
                    <Button type="primary" full :label="ctrans('Agree and connect')" :loading="isBusy" :disabled="isBusy" @click="agreeAndConnect">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
                <button type="button" class="self-start text-xs text-gray-500 underline focus:outline-none" @click="showDetails = !showDetails">{{ ctrans("Privacy policy") }}</button>
                <SlackConsentDetails v-if="showDetails" />
            </template>

            <template v-else>
                <p v-if="error" class="text-xs text-red-600" role="alert">{{ error }}</p>
                <Button type="primary" full :label="ctrans('Bring me in')" :loading="isBusy" :disabled="isBusy" @click="startWithoutSlack" />

                <div class="space-y-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                    <p class="flex items-center gap-x-1.5 font-semibold text-gray-600">
                        <FontAwesomeIcon :icon="faSlack" class="text-gray-400" fixed-width aria-hidden="true" />
                        {{ ctrans("Connect your Slack") }}
                    </p>
                    <p>{{ ctrans("Have you used :company's Slack before? If yes, we'll bring your messages to Verba.", { company: join.company }) }}</p>
                    <p v-if="linkTicket" class="rounded-md bg-green-50 px-3 py-2 text-green-800">{{ ctrans("Done. The help desk has your request (:reference) and will tell you when you can come back.", { reference: linkTicket }) }}</p>
                    <Button type="tertiary" full :label="ctrans('Yes, I used :company\'s Slack: bring my messages', { company: join.company })" :disabled="isBusy || !!linkTicket" @click="askHelpDeskToLink" />
                </div>
            </template>

        </div>
    </div>
</template>
