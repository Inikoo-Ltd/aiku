<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 17 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { nextTick, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserPlus, faTimes } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"

library.add(faUserPlus, faTimes)

interface Person {
    id: number
    name: string
    avatar: any
}

const props = defineProps<{
    modelValue: Person[]
    excludeIds?: number[]
    compact?: boolean
}>()

const emit = defineEmits<{
    "update:modelValue": [people: Person[]]
}>()

const isSearching = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)

const startSearching = async () => {
    isSearching.value = true
    await nextTick()
    searchInput.value?.focus()
}
const query = ref("")
const results = ref<Person[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const onInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    if (query.value.trim().length < 2) {
        results.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: query.value.trim() } })
        const takenIds = [...(props.excludeIds ?? []), ...props.modelValue.map((person) => person.id)]
        results.value = data.data.filter((person: Person) => !takenIds.includes(person.id))
    }, 250)
}

const add = (person: Person) => {
    emit("update:modelValue", [...props.modelValue, { id: person.id, name: person.name, avatar: person.avatar }])
    query.value = ""
    results.value = []
    isSearching.value = false
}

const remove = (person: Person) => emit("update:modelValue", props.modelValue.filter((p) => p.id !== person.id))
</script>

<template>
    <div class="relative flex flex-wrap items-center gap-1">
        <span v-for="person in modelValue" :key="person.id" class="flex items-center gap-x-1 pl-0.5 pr-1.5 py-0.5 rounded-full bg-gray-100 text-xs text-gray-700">
            <span class="h-4 w-4 rounded-full overflow-hidden bg-gray-200 shrink-0">
                <Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover />
            </span>
            {{ person.name }}
            <button type="button" v-tooltip="trans('Remove')" class="text-gray-400 hover:text-red-600" @click="remove(person)">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </span>
        <input
            v-if="isSearching"
            ref="searchInput"
            v-model="query"
            type="text"
            :placeholder="trans('Search colleague…')"
            class="px-2 py-0.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]"
            @input="onInput"
            @keydown.esc.stop="isSearching = false"
            @blur="isSearching = false" />
        <button v-else type="button" v-tooltip="trans('Add a colleague to work on this too')" class="flex items-center gap-x-1 text-xs text-gray-400 hover:text-[--app-accent]" @click="startSearching">
            <FontAwesomeIcon icon="fal fa-user-plus" fixed-width aria-hidden="true" />
            <span v-if="!compact">{{ trans('Add colleague') }}</span>
        </button>
        <div v-if="results.length" class="absolute top-full left-0 z-10 mt-1 w-56 bg-white border border-gray-200 rounded-md shadow max-h-48 overflow-y-auto">
            <button v-for="person in results" :key="person.id" type="button" class="w-full flex items-center gap-x-2 px-3 py-2 hover:bg-gray-50 text-left" @mousedown.prevent="add(person)">
                <span class="h-6 w-6 rounded-full overflow-hidden bg-gray-200 shrink-0">
                    <Image v-if="person.avatar" :src="person.avatar" :alt="person.name" image-cover />
                </span>
                <span class="text-sm truncate">{{ person.name }}</span>
            </button>
        </div>
    </div>
</template>
