<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faPlus, faTimes, faUsers } from "@fal"

interface DirectoryChannel {
    id: number
    name: string | null
    topic: string | null
    member_count: number
    is_mine: boolean
}

const visible = defineModel<boolean>("visible", { default: false })
const props = defineProps<{ startCreating?: boolean }>()
const emit = defineEmits<{ created: [url: string]; joined: [] }>()

const isCreating = ref(false)
const channels = ref<DirectoryChannel[] | null>(null)
const filter = ref("")
const listError = ref<string | null>(null)
const joining = ref<number | null>(null)

const name = ref("")
const description = ref("")
const formError = ref<string | null>(null)
const isSaving = ref(false)
const createdUrl = ref<string | null>(null)

const slackName = (text: string) => text.toLowerCase().replace(/\s+/g, "-").replace(/[^\p{Ll}\p{Lo}\p{N}_-]/gu, "").slice(0, 80)
watch(name, (typed) => {
    if (slackName(typed) !== typed) name.value = slackName(typed)
})

const shown = computed(() => {
    const needle = filter.value.trim().toLowerCase()
    return (channels.value ?? []).filter((channel) => !needle || (channel.name ?? "").includes(needle) || (channel.topic ?? "").toLowerCase().includes(needle))
})

const load = async () => {
    channels.value = null
    listError.value = null
    try {
        const { data } = await axios.get(route("grp.chat.staff.slack.directory"))
        channels.value = data.data
    } catch {
        channels.value = []
        listError.value = ctrans("The V·Desks could not be loaded. Try again.")
    }
}

watch(visible, (open) => {
    if (!open) return
    isCreating.value = !!props.startCreating
    filter.value = ""
    name.value = ""
    description.value = ""
    formError.value = null
    createdUrl.value = null
    load()
})

const firstError = (exception: any, fallback: string) => {
    const errors = exception?.response?.data?.errors
    return (errors && (errors.name?.[0] ?? errors.description?.[0] ?? errors.slack?.[0])) ?? exception?.response?.data?.message ?? fallback
}

const join = async (channel: DirectoryChannel) => {
    joining.value = channel.id
    listError.value = null
    try {
        const { data } = await axios.patch(route("grp.chat.staff.slack.channels.update", channel.id), { operation: "join" })
        channel.is_mine = true
        channel.member_count = data.member_count
        emit("joined")
    } catch (exception) {
        listError.value = firstError(exception, ctrans("Could not join. Try again."))
    } finally {
        joining.value = null
    }
}

const create = async () => {
    if (createdUrl.value) {
        visible.value = false
        emit("created", createdUrl.value)
        return
    }
    if (!name.value || isSaving.value) return
    isSaving.value = true
    formError.value = null
    try {
        const { data } = await axios.post(route("grp.chat.staff.slack.channels.store"), { name: name.value, description: description.value.trim() || null })
        if (data.warning) {
            formError.value = data.warning
            createdUrl.value = data.url
            return
        }
        visible.value = false
        emit("created", data.url)
    } catch (exception) {
        formError.value = firstError(exception, ctrans("The V·Desk could not be created. Try again."))
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="isCreating ? ctrans('New public V·Desk') : ctrans('Browse V·Desks')" :style="{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div v-if="isCreating" class="space-y-4 text-sm">
            <p class="text-gray-500">{{ ctrans("A Slack public channel, created as you: everyone in Verba and Slack can find it and join.") }}</p>
            <label class="block space-y-1">
                <span class="font-medium text-gray-700">{{ ctrans("Name") }} <span class="text-red-500">*</span></span>
                <InputText v-model="name" class="w-full" maxlength="80" :placeholder="ctrans('e.g. buying-team')" autofocus @keydown.enter="create" />
                <span class="block text-xs text-gray-400">{{ ctrans("Lower case letters, numbers, hyphens and underscores, as Slack needs") }}</span>
            </label>
            <label class="block space-y-1">
                <span class="font-medium text-gray-700">{{ ctrans("Description (optional)") }}</span>
                <Textarea v-model="description" class="w-full" rows="2" maxlength="250" auto-resize />
            </label>
            <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
        </div>

        <div v-else class="space-y-3 text-sm">
            <div class="flex gap-2">
                <InputText v-model="filter" class="min-w-0 flex-1" :placeholder="ctrans('Find a V·Desk')" autofocus />
                <Button type="tertiary" class="shrink-0" :label="ctrans('New')" @click="isCreating = true">
                    <template #icon>
                        <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <p v-if="listError" class="text-xs text-red-600">{{ listError }}</p>
            <div v-if="channels === null" class="flex justify-center py-6"><LoadingIcon /></div>
            <p v-else-if="!shown.length" class="py-6 text-center text-gray-400">{{ ctrans("No V·Desks found") }}</p>
            <div v-else class="max-h-[55vh] divide-y divide-gray-100 overflow-y-auto">
                <div v-for="channel in shown" :key="channel.id" class="flex items-center gap-x-3 py-2">
                    <Link :href="route('grp.chat.staff.slack.show', channel.id)" class="min-w-0 flex-1" @click="visible = false">
                        <span class="block truncate font-semibold text-gray-800"># {{ channel.name }}</span>
                        <span class="flex items-center gap-x-2 text-xs text-gray-500">
                            <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap"><FontAwesomeIcon :icon="faUsers" fixed-width aria-hidden="true" />{{ channel.member_count }}</span>
                            <span v-if="channel.topic" class="truncate">{{ channel.topic }}</span>
                        </span>
                    </Link>
                    <span v-if="channel.is_mine" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-xs text-gray-500">
                        <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />{{ ctrans("Joined") }}
                    </span>
                    <Button v-else type="tertiary" size="xs" class="shrink-0" :label="ctrans('Join')" :loading="joining === channel.id" :disabled="joining !== null" @click="join(channel)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </div>

        <template v-if="isCreating" #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="startCreating ? (visible = false) : (isCreating = false)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="primary" full :label="createdUrl ? ctrans('Open V·Desk') : ctrans('Create')" :loading="isSaving" :disabled="!name || isSaving" @click="create">
                    <template #icon>
                        <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
