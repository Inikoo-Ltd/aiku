<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { AutoComplete, Dialog, InputText, Popover, Textarea } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArchive, faCheck, faEllipsisV, faPencil, faSignOut, faSignIn, faTimes, faUserPlus } from "@fal"
import type { SlackWriteState } from "@/Components/Messaging/Slack/SlackMessageComposer.vue"

const props = defineProps<{
    channel: { id: number; name: string | null; slack_name?: string | null; topic: string | null; purpose?: string | null; is_mine?: boolean | null }
    write: SlackWriteState
}>()

type Operation = "join" | "leave" | "invite" | "rename" | "topic" | "purpose" | "archive"

const menu = ref()
const dialog = ref<"details" | "people" | "archive">("details")
const isDialogOpen = ref(false)
const isBusy = ref(false)
const error = ref<string | null>(null)

const name = ref("")
const topic = ref("")
const purpose = ref("")
const people = ref<{ id: string; label: string }[]>([])
const suggestions = ref<{ id: string; label: string }[]>([])

const reload = () => router.reload({ only: ["slack_channel", "slack_channels"] })

const send = async (operation: Operation, body: Record<string, unknown> = {}) => {
    await axios.patch(route("grp.chat.staff.slack.channels.update", props.channel.id), { operation, ...body })
}

const run = async (steps: [Operation, Record<string, unknown>?][]) => {
    isBusy.value = true
    error.value = null
    let done = 0
    try {
        for (const [operation, body] of steps) {
            await send(operation, body)
            done++
        }
        isDialogOpen.value = false
        menu.value?.hide()
    } catch (exception: any) {
        const errors = exception?.response?.data?.errors
        error.value = (errors && (errors.name?.[0] ?? errors.text?.[0] ?? errors.slack?.[0] ?? errors.slack_user_ids?.[0])) ?? ctrans("Slack did not accept this. Try again later")
    } finally {
        isBusy.value = false
        if (done || !error.value || steps[0]?.[0] === "invite") reload()
    }
}

const open = (which: "details" | "people" | "archive") => {
    error.value = null
    name.value = props.channel.slack_name ?? props.channel.name ?? ""
    topic.value = props.channel.topic ?? ""
    purpose.value = props.channel.purpose ?? ""
    people.value = []
    menu.value?.hide()
    dialog.value = which
    isDialogOpen.value = true
}

const detailSteps = computed(() => {
    const steps: [Operation, Record<string, unknown>][] = []
    if (name.value && name.value !== (props.channel.slack_name ?? props.channel.name)) steps.push(["rename", { name: name.value }])
    if (topic.value.trim() !== (props.channel.topic ?? "")) steps.push(["topic", { text: topic.value.trim() }])
    if (purpose.value.trim() !== (props.channel.purpose ?? "")) steps.push(["purpose", { text: purpose.value.trim() }])
    return steps
})

const search = (event: { query: string }) => {
    const needle = event.query.toLowerCase()
    const chosen = new Set(people.value.map((person) => person.id))
    suggestions.value = (props.write.people ?? []).filter((person) => !person.id.startsWith("!") && !chosen.has(person.id) && person.label.toLowerCase().includes(needle)).slice(0, 20)
}

const slackName = (text: string) => text.toLowerCase().replace(/\s+/g, "-").replace(/[^\p{Ll}\p{Lo}\p{N}_-]/gu, "").slice(0, 80)
watch(name, (typed) => {
    if (slackName(typed) !== typed) name.value = slackName(typed)
})
</script>

<template>
    <div class="shrink-0">
    <button
        type="button"
        v-tooltip="ctrans('Channel settings')"
        :aria-label="ctrans('Channel settings')"
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-600 transition duration-200 hover:bg-gray-100"
        @click="(event: Event) => { error = null; menu?.toggle(event) }">
        <FontAwesomeIcon :icon="faEllipsisV" fixed-width aria-hidden="true" />
    </button>

    <Popover ref="menu">
        <div class="w-60 text-sm">
            <p v-if="write.reason && !write.stand_in" class="px-2 pb-2 text-xs text-gray-600">{{ write.reason }}</p>
            <p v-else-if="write.reason" class="px-2 pb-2 text-xs text-gray-600">
                {{ ctrans("Connect your Slack account to change this channel") }}
                <a v-if="write.connect_url" :href="write.connect_url" class="ml-1 font-medium text-[var(--app-accent)] hover:underline">{{ ctrans("Connect Slack") }}</a>
            </p>
            <button v-if="channel.is_mine === false" type="button" class="flex w-full items-center gap-x-2 rounded px-2 py-1.5 text-left hover:bg-gray-100 disabled:opacity-50" :disabled="isBusy" @click="run([['join']])">
                <FontAwesomeIcon :icon="faSignIn" class="text-green-600" fixed-width aria-hidden="true" />{{ ctrans("Join in Slack") }}
            </button>
            <button type="button" class="flex w-full items-center gap-x-2 rounded px-2 py-1.5 text-left hover:bg-gray-100" @click="open('people')">
                <FontAwesomeIcon :icon="faUserPlus" fixed-width aria-hidden="true" />{{ ctrans("Add people") }}
            </button>
            <button type="button" class="flex w-full items-center gap-x-2 rounded px-2 py-1.5 text-left hover:bg-gray-100" @click="open('details')">
                <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />{{ ctrans("Name, topic and description") }}
            </button>
            <button v-if="channel.is_mine" type="button" class="flex w-full items-center gap-x-2 rounded px-2 py-1.5 text-left hover:bg-gray-100 disabled:opacity-50" :disabled="isBusy" @click="run([['leave']])">
                <FontAwesomeIcon :icon="faSignOut" fixed-width aria-hidden="true" />{{ ctrans("Leave in Slack") }}
            </button>
            <button type="button" class="flex w-full items-center gap-x-2 rounded px-2 py-1.5 text-left text-amber-700 hover:bg-gray-100" @click="open('archive')">
                <FontAwesomeIcon :icon="faArchive" fixed-width aria-hidden="true" />{{ ctrans("Archive") }}
            </button>
            <p v-if="error && !isDialogOpen" class="px-2 pt-1 text-xs text-red-600">{{ error }}</p>
        </div>
    </Popover>

    <Dialog :visible="isDialogOpen" modal :header="dialog === 'details' ? ctrans('Name, topic and description') : dialog === 'people' ? ctrans('Add people to #:channel', { channel: channel.name ?? '' }) : ctrans('Archive #:channel?', { channel: channel.name ?? '' })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false" @update:visible="(value) => (isDialogOpen = value)">
        <div class="space-y-4 text-sm">
            <template v-if="dialog === 'details'">
                <label class="block space-y-1">
                    <span class="font-medium text-gray-700">{{ ctrans("Name") }} <span class="text-red-500">*</span></span>
                    <InputText v-model="name" class="w-full" maxlength="80" />
                </label>
                <label class="block space-y-1">
                    <span class="font-medium text-gray-700">{{ ctrans("Topic (optional)") }}</span>
                    <InputText v-model="topic" class="w-full" maxlength="250" />
                </label>
                <label class="block space-y-1">
                    <span class="font-medium text-gray-700">{{ ctrans("Description (optional)") }}</span>
                    <Textarea v-model="purpose" class="w-full" rows="2" maxlength="250" auto-resize />
                </label>
            </template>
            <template v-else-if="dialog === 'people'">
                <label class="block space-y-1">
                    <span class="font-medium text-gray-700">{{ ctrans("People") }} <span class="text-red-500">*</span></span>
                    <AutoComplete v-model="people" multiple fluid :suggestions="suggestions" optionLabel="label" dataKey="id" :placeholder="people.length ? '' : ctrans('Type a name')" @complete="search" />
                </label>
            </template>
            <p v-else class="text-gray-600">{{ ctrans("Nobody can write in it any more, in Slack or Verba. Its messages stay readable.") }}</p>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isDialogOpen = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-if="dialog === 'archive'" type="tertiary" full :label="ctrans('Archive')" :loading="isBusy" @click="run([['archive']])">
                    <template #icon>
                        <FontAwesomeIcon :icon="faArchive" class="text-amber-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-else-if="dialog === 'people'" type="primary" full :label="ctrans('Add')" :loading="isBusy" :disabled="!people.length" @click="run([['invite', { slack_user_ids: people.map((person) => person.id) }]])">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-else type="primary" full :label="ctrans('Save')" :loading="isBusy" :disabled="!name || !detailSteps.length" @click="run(detailSteps)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
    </div>
</template>
