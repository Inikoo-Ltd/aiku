<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from "vue"
import { Head, usePage } from "@inertiajs/vue3"
import axios from "axios"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVideo, faSignIn, faDoorOpen, faCheck, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import GrpAuth from "@/Layouts/GrpAuth.vue"
import { ctrans } from "@/Composables/useTrans"
import { useColorTheme } from "@/Composables/useStockList"
import { setColorStyleRoot } from "@/Composables/useApp"
import { useAppAccentVariables } from "@/Composables/useAppAccent"
import { useStaffCalls } from "@/Stores/staff-calls"
import { useEchoGrpPersonal } from "@/Stores/echo-grp-personal"

library.add(faVideo, faSignIn, faDoorOpen, faCheck, faTimes)

defineOptions({ layout: (_h: unknown, page: unknown) => page })

const StaffCallPanel = defineAsyncComponent(() => import("@/Components/Messaging/Call/StaffCallPanel.vue"))

const props = defineProps<{
    meeting: { code: string; title: string | null; is_open: boolean; is_locked: boolean }
    is_colleague: boolean
    is_creator: boolean
    login_url: string
    routes: { join: string; end: string; knock: string; poll: string; token: string; leave: string }
}>()

setColorStyleRoot(useColorTheme[0])
useAppAccentVariables(() => useColorTheme[0])

const store = useStaffCalls()

type Stage = "start" | "waiting" | "denied" | "joining" | "in_call" | "left" | "ended"
const stage = ref<Stage>(props.meeting.is_open ? "start" : "ended")
const name = ref("")
const error = ref<string | null>(null)
const busy = ref(false)
let guest: { ulid: string; secret: string } | null = null
let pollTimer: ReturnType<typeof setTimeout> | null = null

const title = computed(() => props.meeting.title || ctrans("Meeting"))
const guestUrl = (template: string) => template.replace("__guest__", guest!.ulid)

const stopPolling = () => {
    if (pollTimer) clearTimeout(pollTimer)
    pollTimer = null
}

const joinAsColleague = async (withCamera: boolean) => {
    error.value = null
    await store.joinMeeting(props.routes.join, withCamera)
    if (store.active) {
        stage.value = "in_call"
    } else {
        error.value = store.meetingError
    }
}

const enterAsGuest = async () => {
    stopPolling()
    stage.value = "joining"
    try {
        const { data } = await axios.post(guestUrl(props.routes.token), { secret: guest!.secret })
        store.joinAsGuest(
            { call: data.call, token: data.token, url: data.url, withCamera: false },
            { pollUrl: guestUrl(props.routes.poll), leaveUrl: guestUrl(props.routes.leave), secret: guest!.secret },
        )
        stage.value = "in_call"
    } catch (failure: any) {
        const status = failure?.response?.status
        if (status === 403 || status === 404) {
            stage.value = "ended"
            return
        }
        stage.value = "waiting"
        pollTimer = setTimeout(poll, 5000)
    }
}

const poll = async () => {
    if (!guest) return
    try {
        const { data } = await axios.post(guestUrl(props.routes.poll), { secret: guest.secret })
        if (data.status === "admitted" && data.call_live) return enterAsGuest()
        if (data.status === "denied" || data.status === "removed") {
            stage.value = "denied"
            return stopPolling()
        }
        if (!data.meeting_open || data.status === "left") {
            stage.value = "ended"
            return stopPolling()
        }
        pollTimer = setTimeout(poll, data.poll_seconds * 1000)
    } catch {
        pollTimer = setTimeout(poll, 8000)
    }
}

const knock = async () => {
    error.value = null
    busy.value = true
    try {
        const { data } = await axios.post(props.routes.knock, { name: name.value })
        guest = { ulid: data.guest, secret: data.secret }
        stage.value = "waiting"
        pollTimer = setTimeout(poll, data.poll_seconds * 1000)
    } catch (failure: any) {
        error.value = failure?.response?.status === 429
            ? ctrans("Too many tries. Wait a moment and try again.")
            : failure?.response?.data?.errors?.name?.[0] ?? ctrans("Could not ask to join. Try again.")
    } finally {
        busy.value = false
    }
}

const endLink = async () => {
    error.value = null
    try {
        await axios.delete(props.routes.end)
        stage.value = "ended"
    } catch {
        error.value = ctrans("Could not end the meeting link. Try again.")
    }
}

const cancelKnock = async () => {
    stopPolling()
    if (guest) await axios.post(guestUrl(props.routes.leave), { secret: guest.secret }).catch(() => { })
    guest = null
    stage.value = "start"
}

store.$subscribe(() => {
    if (stage.value === "in_call" && !store.active) {
        stage.value = "left"
    }
})

onMounted(() => {
    const userId = (usePage().props as any)?.auth?.user?.id
    if (props.is_colleague && userId) {
        useEchoGrpPersonal().subscribe(userId)
    }
})

onBeforeUnmount(stopPolling)
</script>

<template>
    <Head :title="title" />

    <GrpAuth v-if="stage !== 'in_call'">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon icon="fal fa-video" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                <h1 class="truncate text-base font-semibold text-gray-800">{{ title }}</h1>
            </div>

            <template v-if="stage === 'ended'">
                <p class="text-sm text-gray-600">{{ ctrans("This meeting has ended.") }}</p>
            </template>

            <template v-else-if="stage === 'left'">
                <p class="text-sm text-gray-600">{{ ctrans("You left the meeting.") }}</p>
                <Button v-if="meeting.is_open" type="tertiary" :label="ctrans('Join again')" @click="stage = 'start'">
                    <template #icon><FontAwesomeIcon icon="fal fa-door-open" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>

            <template v-else-if="stage === 'denied'">
                <p class="text-sm text-gray-600">{{ ctrans("You were not let into this meeting.") }}</p>
            </template>

            <template v-else-if="stage === 'waiting' || stage === 'joining'">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <LoadingIcon />
                    {{ stage === "joining" ? ctrans("Joining…") : ctrans("Waiting for someone in the meeting to let you in…") }}
                </div>
                <Button v-if="stage === 'waiting'" type="tertiary" :label="ctrans('Cancel')" @click="cancelKnock">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>

            <template v-else-if="is_colleague">
                <p class="text-sm text-gray-600">{{ ctrans("You are logged in to aiku, so you go straight in.") }}</p>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Join with camera')" :disabled="!!store.joining" @click="joinAsColleague(true)">
                        <template #icon><FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Join')" :loading="!!store.joining" @click="joinAsColleague(false)">
                        <template #icon><FontAwesomeIcon icon="fal fa-door-open" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
                <button v-if="is_creator" type="button" class="self-start text-xs text-gray-500 hover:text-gray-700" @click="endLink">
                    {{ ctrans("End this meeting link for good") }}
                </button>
            </template>

            <template v-else>
                <p v-if="meeting.is_locked" class="text-sm text-gray-600">{{ ctrans("This meeting is locked. Ask the people in it to unlock it.") }}</p>
                <form v-else class="flex flex-col gap-2" @submit.prevent="knock">
                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-600">{{ ctrans("Your name") }} <span class="text-red-500">*</span></span>
                        <InputText v-model="name" maxlength="60" autocomplete="name" :aria-label="ctrans('Your name')" />
                    </label>
                    <Button type="primary" full :label="ctrans('Ask to join')" :loading="busy" :disabled="name.trim().length < 2" @click="knock">
                        <template #icon><FontAwesomeIcon icon="fal fa-door-open" fixed-width aria-hidden="true" /></template>
                    </Button>
                </form>
                <a :href="login_url" class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700">
                    <FontAwesomeIcon icon="fal fa-sign-in" fixed-width aria-hidden="true" />
                    {{ ctrans("Work here? Log in to aiku to go straight in") }}
                </a>
            </template>

            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        </div>
    </GrpAuth>

    <div v-else class="min-h-screen bg-gray-950">
        <StaffCallPanel v-if="store.active" :key="store.active.call.ulid" :join="store.active" start-expanded />
    </div>
</template>
