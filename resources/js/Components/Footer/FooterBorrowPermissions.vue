<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUserShield } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import { router, usePage } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { ctrans } from "@/Composables/useTrans"

library.add(faUserShield, faSpinnerThird)

interface Lender {
    id: number
    username: string
    contact_name: string | null
}

const loggedUser = computed(() => usePage().props?.auth?.user)
const borrowedFrom = computed<Lender | null>(() => loggedUser.value?.borrowed_permissions_from ?? null)

const search = ref("")
const lenders = ref<Lender[]>([])
const isLoading = ref(false)
const cachedLenders = new Map<string, Lender[]>()
const switchingTo = ref<number | "self" | null>(null)

const fetchLenders = async () => {
    const term = search.value
    const cached = cachedLenders.get(term)
    if (cached) {
        lenders.value = cached
    }
    isLoading.value = !cached
    try {
        const response = await axios.get(route("grp.profile.borrowable_users.index"), { params: { search: term } })
        cachedLenders.set(term, response.data)
        if (search.value === term) {
            lenders.value = response.data
        }
    } finally {
        isLoading.value = false
    }
}

const fetchLendersDebounced = debounce(fetchLenders, 300)

watch(search, (term) => cachedLenders.has(term) ? fetchLenders() : fetchLendersDebounced())

const borrowFrom = (lender: Lender) => {
    switchingTo.value = lender.id
    router.post(route("grp.models.user.borrow_permissions", { user: lender.id }), {}, { onError: () => (switchingTo.value = null) })
}
const stopBorrowing = () => {
    switchingTo.value = "self"
    router.delete(route("grp.profile.borrowed_permissions.delete"), { onError: () => (switchingTo.value = null) })
}
</script>

<template>
    <Popover v-if="loggedUser?.can_borrow_permissions" v-slot="{ open }" class="relative h-full">
        <PopoverButton
            @click="!open && fetchLenders()"
            :class="[borrowedFrom ? 'bg-amber-500 text-black' : open ? 'bg-gray-800 text-white' : 'hover:bg-gray-800 text-gray-200']"
            class="inline-flex items-center gap-x-1 px-3 h-full outline-none focus:outline-none focus:ring-0"
            v-tooltip="ctrans('Use the system with another user\'s permissions')">
            <FontAwesomeIcon v-if="switchingTo" icon="fad fa-spinner-third" class="animate-spin text-xs" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else icon="fal fa-user-shield" class="text-xs" fixed-width aria-hidden="true" />
            <span class="text-xs leading-none" :class="borrowedFrom ? 'font-semibold' : 'font-extralight'">
                {{ borrowedFrom ? (borrowedFrom.contact_name || borrowedFrom.username) : ctrans("Impersonate") }}
            </span>
        </PopoverButton>

        <transition name="headlessui">
            <PopoverPanel class="absolute bottom-full right-0 z-10 w-80 rounded-t border border-gray-300 border-b-0 bg-gray-800 text-xs text-white shadow-lg">
                <button v-if="borrowedFrom" type="button" @click="stopBorrowing" :disabled="!!switchingTo"
                    class="w-full bg-amber-500 px-2 py-1.5 font-semibold text-black hover:bg-amber-400 disabled:cursor-wait">
                    <FontAwesomeIcon v-if="switchingTo === 'self'" icon="fad fa-spinner-third" class="animate-spin mr-1" fixed-width aria-hidden="true" />
                    {{ ctrans("Back to my permissions") }}
                </button>

                <div class="p-2">
                    <input v-model="search" type="text" :placeholder="ctrans('Search user')"
                        class="w-full rounded border-gray-500 bg-gray-700 px-2 py-1 text-xs text-white placeholder-gray-400" />
                </div>

                <div class="max-h-64 overflow-y-auto pb-1">
                    <div v-if="isLoading" class="px-2 py-1.5 text-gray-400">{{ ctrans("Loading") }}…</div>
                    <div v-else-if="!lenders.length" class="px-2 py-1.5 text-gray-400">{{ ctrans("Nothing to show here") }}</div>
                    <button v-else v-for="lender in lenders" :key="lender.id" type="button" @click="borrowFrom(lender)" :disabled="!!switchingTo"
                        :class="[lender.id === borrowedFrom?.id ? 'text-amber-400' : 'hover:bg-white/20', switchingTo === lender.id ? 'bg-white/20' : '']"
                        class="grid w-full grid-cols-[1rem_7rem_1fr] items-center gap-x-1 px-2 py-1.5 text-left disabled:cursor-wait">
                        <FontAwesomeIcon v-if="switchingTo === lender.id" icon="fad fa-spinner-third" class="animate-spin text-amber-400" fixed-width aria-hidden="true" />
                        <span v-else />
                        <span class="truncate font-semibold">{{ lender.username }}</span>
                        <span class="truncate text-gray-400" :title="lender.contact_name ?? ''">{{ lender.contact_name }}</span>
                    </button>
                </div>
            </PopoverPanel>
        </transition>
    </Popover>
</template>
