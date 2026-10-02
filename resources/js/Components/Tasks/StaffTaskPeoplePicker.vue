<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Popover } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketPersonChip from "@/Components/Tickets/TicketPersonChip.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserPlus, faCheckSquare, faSquare, faSpinner } from "@fal"
library.add(faUserPlus, faCheckSquare, faSquare, faSpinner)

type Person = { id: number; name: string; avatar: any }

const props = withDefaults(defineProps<{
    modelValue: number[]
    options: Person[]
    excludeIds?: number[]
    editable?: boolean
    pending?: boolean
    canRemove?: (personId: number) => boolean
}>(), { excludeIds: () => [], editable: true, pending: false, canRemove: undefined })

const emit = defineEmits<{
    "update:modelValue": [ids: number[]]
    hide: []
}>()

const popover = ref()
const isOpen = ref(false)
const query = ref("")

const selectedPeople = computed(() =>
    props.modelValue.map((id) => props.options.find((person) => person.id === id)).filter((person): person is Person => !!person)
)

const candidates = computed(() => {
    const search = query.value.trim().toLowerCase()
    return props.options.filter((person) => !props.excludeIds.includes(person.id) && (!search || person.name.toLowerCase().includes(search)))
})

const isRemovable = (personId: number) => props.editable && (props.canRemove ? props.canRemove(personId) : true)

const isLocked = (personId: number) => props.modelValue.includes(personId) && !isRemovable(personId)

const toggle = (personId: number) => {
    if (props.pending || isLocked(personId)) return
    emit("update:modelValue", props.modelValue.includes(personId) ? props.modelValue.filter((id) => id !== personId) : [...props.modelValue, personId])
}

const removingId = ref<number | null>(null)

watch(() => props.pending, (isPending) => {
    if (!isPending) removingId.value = null
})

const removingPerson = computed(() => removingId.value !== null && !props.modelValue.includes(removingId.value)
    ? props.options.find((person) => person.id === removingId.value) ?? null
    : null)

const remove = (personId: number) => {
    if (props.pending || !isRemovable(personId)) return
    removingId.value = personId
    emit("update:modelValue", props.modelValue.filter((id) => id !== personId))
    emit("hide")
}

const onHide = () => {
    isOpen.value = false
    query.value = ""
    emit("hide")
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <TicketPersonChip
                v-for="person in selectedPeople"
                :key="person.id"
                :name="person.name"
                :avatar="person.avatar"
                :removable="!pending && !isOpen && isRemovable(person.id)"
                :removing="removingId === person.id"
                @remove="remove(person.id)" />
            <TicketPersonChip
                v-if="removingPerson"
                :key="`removing-${removingPerson.id}`"
                :name="removingPerson.name"
                :avatar="removingPerson.avatar"
                removing />
            <button
                v-if="editable"
                v-tooltip="ctrans('Add or remove colleagues')"
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-full border border-dashed border-gray-300 text-sm text-gray-500 transition duration-200 hover:border-[--app-accent] hover:text-[--app-accent-strong] active:!border-[--app-accent] active:!text-[--app-accent-strong]"
                :class="isOpen && '!border-[--app-accent] !bg-[--app-accent-soft] !text-[--app-accent-strong]'"
                @click="popover.toggle($event)">
                <FontAwesomeIcon :icon="pending ? 'fal fa-spinner' : 'fal fa-user-plus'" :spin="pending" fixed-width />
            </button>
            <span v-else-if="!selectedPeople.length" class="text-sm text-gray-400">-</span>
        </div>
        <Popover v-if="editable" ref="popover" @show="isOpen = true" @hide="onHide">
            <div class="w-64 space-y-2 text-sm">
                <input v-model="query" type="text" class="w-full rounded border-gray-300 text-sm" :placeholder="ctrans('Search colleague…')" />
                <div class="flex max-h-72 flex-col overflow-y-auto">
                    <button
                        v-for="person in candidates"
                        :key="person.id"
                        type="button"
                        :disabled="isLocked(person.id)"
                        v-tooltip="isLocked(person.id) ? ctrans('Only the assignee or the requester can remove :name', { name: person.name }) : undefined"
                        class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:bg-transparent"
                        @click="toggle(person.id)">
                        <FontAwesomeIcon :icon="modelValue.includes(person.id) ? 'fal fa-check-square' : 'fal fa-square'" fixed-width :class="modelValue.includes(person.id) ? 'text-[--app-accent-strong]' : 'text-gray-400'" />
                        <TicketUserAvatar :name="person.name" :avatar="person.avatar" size="sm" />
                        <span class="truncate">{{ person.name }}</span>
                    </button>
                    <p v-if="!candidates.length" class="p-2 text-gray-400">{{ ctrans("Nobody to add") }}</p>
                </div>
            </div>
        </Popover>
    </div>
</template>
