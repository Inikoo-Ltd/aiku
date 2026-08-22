<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faPlus, faUser } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { useLiveUsers } from "@/Stores/active-users"
import type { StaffCoworker } from "@/Stores/staff-messaging"

library.add(faTimes, faPlus, faUser)

const props = defineProps<{
    isOpen: boolean
}>()

const emit = defineEmits<{
    close: []
    changed: []
}>()

const search = ref("")
const searchInput = ref<HTMLInputElement | null>(null)
const coworkers = ref<StaffCoworker[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const isOnline = (id: number) => !!useLiveUsers().liveUsers[id]

const fetchCoworkers = async (q: string) => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: q ? { q } : {} })
    coworkers.value = data.data
}

const onSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchCoworkers(search.value), 300)
}

const teamMembers = computed(() => coworkers.value.filter((c) => c.in_team))
const otherCoworkers = computed(() => coworkers.value.filter((c) => !c.in_team).slice(0, 20))

const toggleTeam = async (coworker: StaffCoworker) => {
    const { data } = await axios.post(route("grp.chat.staff.team.toggle"), { user_id: coworker.id })
    coworker.in_team = data.in_team
    emit("changed")
}

const closeModal = () => emit("close")

onMounted(() => {
    fetchCoworkers("")
    setTimeout(() => searchInput.value?.focus(), 50)
})
</script>

<template>
    <Modal :is-open="isOpen" width="w-full max-w-md" @on-close="closeModal">
        <div class="flex items-center justify-between mb-4">
            <span class="text-base font-semibold text-gray-900">{{ trans('My team') }}</span>
            <button class="text-gray-400 hover:text-gray-600" @click="closeModal">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </div>

        <input
            ref="searchInput"
            v-model="search"
            type="text"
            :placeholder="trans('Find a coworker…')"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500 mb-4"
            @input="onSearchInput" />

        <div class="text-xs text-gray-400 mb-1">{{ trans('In my team') }} ({{ teamMembers.length }})</div>
        <div class="h-32 overflow-y-auto mb-4 divide-y divide-gray-100">
            <div v-for="coworker in teamMembers" :key="'team-' + coworker.id" class="flex items-center gap-x-2 py-2">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-gray-100 shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-white" :class="isOnline(coworker.id) ? 'bg-green-500' : 'bg-gray-400'" />
                </div>
                <span class="flex-1 text-sm truncate text-gray-900">{{ coworker.name }}</span>
                <button class="text-gray-400 hover:text-red-500 shrink-0" @click="toggleTeam(coworker)" v-tooltip="trans('Remove from my team')">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div v-if="!teamMembers.length" class="text-sm text-gray-400 py-2">{{ trans('No one in your team yet') }}</div>
        </div>

        <div class="text-xs text-gray-400 mb-1">{{ trans('Coworkers') }}</div>
        <div class="h-64 overflow-y-auto divide-y divide-gray-100">
            <div v-for="coworker in otherCoworkers" :key="'other-' + coworker.id" class="flex items-center gap-x-2 py-2">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-gray-100 shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-white" :class="isOnline(coworker.id) ? 'bg-green-500' : 'bg-gray-400'" />
                </div>
                <span class="flex-1 text-sm truncate text-gray-900">{{ coworker.name }}</span>
                <button class="text-gray-400 hover:text-indigo-600 shrink-0" @click="toggleTeam(coworker)" v-tooltip="trans('Add to my team')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div v-if="!otherCoworkers.length" class="text-sm text-gray-400 py-2">{{ trans('No coworkers found') }}</div>
        </div>
    </Modal>
</template>
