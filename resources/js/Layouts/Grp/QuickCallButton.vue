<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import Popover from "primevue/popover"
import InputText from "primevue/inputtext"
import { usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPhone, faComment, faUser } from "@fal"
import { faStar } from "@fas"
import Image from "@/Common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useStaffCalls, type StaffQuickPerson } from "@/Stores/staff-calls"
import { useStaffMessaging } from "@/Stores/staff-messaging"

library.add(faPhone, faComment, faUser, faStar)

const calls = useStaffCalls()
const messaging = useStaffMessaging()
const popover = ref()
const busyId = ref<number | null>(null)
const errorMessage = ref("")
const query = ref("")
const results = ref<StaffQuickPerson[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | undefined

const isSearching = computed(() => query.value.trim().length >= 2)
const shownPeople = computed(() => (isSearching.value ? results.value : calls.quickPeople))

const onSearch = () => {
    clearTimeout(searchTimeout)
    if (!isSearching.value) {
        results.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const myId = usePage().props?.auth?.user?.id
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: query.value.trim() } })
        results.value = data.data
            .filter((person: { id: number }) => person.id !== myId)
            .slice(0, 8)
            .map((person: { id: number; name: string; avatar: Record<string, string> | null }) => ({ ...person, starred: false }))
    }, 250)
}

const act = async (person: StaffQuickPerson, startCall: boolean) => {
    errorMessage.value = ""
    busyId.value = person.id
    try {
        const conversation = await messaging.openWithUser(person.id, { openWindow: !startCall })
        if (startCall) {
            await calls.start(conversation.ulid, "voice")
            if (calls.error?.conversationUlid === conversation.ulid) {
                errorMessage.value = calls.error.text
                return
            }
        }
        popover.value.hide()
    } catch {
        errorMessage.value = ctrans("Could not reach :name. Try again.", { name: person.name })
    } finally {
        busyId.value = null
    }
}
</script>

<template>
    <div v-if="calls.enabled" class="relative">
        <button
            type="button"
            class="h-7 flex items-center rounded-md px-2 text-xs text-gray-500 ring-1 ring-gray-300 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-[var(--app-accent)]"
            :title="ctrans('Quick call')"
            aria-haspopup="true"
            @click="(event) => popover.toggle(event)">
            <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
            <span class="sr-only">{{ ctrans("Quick call") }}</span>
        </button>

        <Popover ref="popover">
            <div class="w-72">
                <p class="mb-2 text-xs font-semibold text-gray-500">{{ ctrans("Call or message") }}</p>
                <InputText v-model="query" size="small" class="mb-2 w-full" :placeholder="ctrans('Find a colleague')" :aria-label="ctrans('Find a colleague')" autofocus @input="onSearch" />
                <ul v-if="shownPeople.length" class="flex flex-col gap-y-1">
                    <li v-for="person in shownPeople" :key="person.id" class="flex items-center gap-x-2 rounded-md px-1 py-1 hover:bg-gray-50">
                        <div class="relative h-8 w-8 shrink-0 overflow-hidden rounded-full bg-gray-100">
                            <Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                        <span class="min-w-0 flex-1 truncate text-sm text-gray-800">{{ person.name }}</span>
                        <FontAwesomeIcon v-if="person.starred" icon="fas fa-star" class="shrink-0 text-xs text-amber-400" fixed-width :title="ctrans('In my team')" />
                        <LoadingIcon v-if="busyId === person.id" class="shrink-0" />
                        <template v-else>
                            <Button type="tertiary" size="xs" :tooltip="ctrans('Message :name', { name: person.name })" :disabled="!!busyId" @click="act(person, false)">
                                <template #icon><FontAwesomeIcon icon="fal fa-comment" fixed-width aria-hidden="true" /></template>
                            </Button>
                            <Button type="tertiary" size="xs" :tooltip="ctrans('Call :name', { name: person.name })" :disabled="!!busyId || !!calls.active" @click="act(person, true)">
                                <template #icon><FontAwesomeIcon icon="fal fa-phone" class="text-green-600" fixed-width aria-hidden="true" /></template>
                            </Button>
                        </template>
                    </li>
                </ul>
                <p v-else-if="isSearching" class="text-xs text-gray-500">{{ ctrans("Nobody matches “:query”", { query: query.trim() }) }}</p>
                <p v-else class="text-xs text-gray-500">{{ ctrans("The people you call, and the colleagues you star in Online now, show here.") }}</p>
                <p v-if="errorMessage" class="mt-2 text-xs text-red-600">{{ errorMessage }}</p>
            </div>
        </Popover>
    </div>
</template>
