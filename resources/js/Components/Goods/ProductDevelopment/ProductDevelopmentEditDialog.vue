<!--
  - Author Louis Perez
  - Created on 16-10-2026-11h-50m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import ProductDevelopmentMarketSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentMarketSelect.vue"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProductDevelopmentUserSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentUserSelect.vue"
import ProductDevelopmentMemberAccessWarning from "@/Components/Goods/ProductDevelopment/ProductDevelopmentMemberAccessWarning.vue"
import ProductDevelopmentRoleSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRoleSelect.vue"
import { peopleForRole, type MemberUserOption } from "@/Components/Goods/ProductDevelopment/productDevelopmentMembers"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTimes } from "@fal"
import { faSave } from "@fas"
library.add(faPlus, faTimes, faSave)

export type ProductDevelopmentEditKind = "markets" | "dates" | "members"

interface Option {
    value: string | number
    label: string
    icon?: string
    color?: string
    avatar?: Record<string, string> | null
}

export interface ProductDevelopmentEditor {
    values: {
        markets: string[]
        target_landing_date: string | null
        target_online_date: string | null
        agent_id: number | null
        members: { user_id: number, role: string }[]
    }
    options: {
        markets: Option[]
        roles: Option[]
        users: Option[]
    }
    routes: {
        update: routeType
        members: routeType
    }
}

const props = defineProps<{
    editor: ProductDevelopmentEditor
}>()

const visible = ref(false)

const peopleFor = (role: string | null) => peopleForRole(props.editor.options.users as MemberUserOption[], role, props.editor.values.agent_id)

function personPlaceholder(role: string | null) {
    if (!role) {
        return ctrans("Choose a role first")
    }
    if (role === "agent" && !props.editor.values.agent_id) {
        return ctrans("Choose the agent first")
    }
    return peopleFor(role).length ? ctrans("Person") : ctrans("Nobody works for this agent")
}

function onRoleChange(member: { user_id: number | null, role: string | null }) {
    const people = peopleFor(member.role)
    if (member.user_id && !people.some((person) => person.value === member.user_id)) {
        member.user_id = null
    }
    if (!member.user_id && member.role === "agent" && people.length === 1) {
        member.user_id = people[0].value
    }
}
const kind = ref<ProductDevelopmentEditKind>("markets")
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const markets = ref<string[]>([])
const landingDate = ref<Date | null>(null)
const onlineDate = ref<Date | null>(null)

watch([landingDate, onlineDate], () => {
    delete errors.value.target_online_date
    if (landingDate.value && onlineDate.value && onlineDate.value < landingDate.value) {
        onlineDate.value = null
    }
})
const members = ref<{ user_id: number | null, role: string | null }[]>([])

const titles = computed<Record<ProductDevelopmentEditKind, string>>(() => ({
    markets: ctrans("Edit markets"),
    dates: ctrans("Edit target dates"),
    members: ctrans("Edit members"),
}))

const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null

function open(editKind: ProductDevelopmentEditKind) {
    kind.value = editKind
    errors.value = {}
    markets.value = [...props.editor.values.markets]
    landingDate.value = props.editor.values.target_landing_date ? new Date(props.editor.values.target_landing_date) : null
    onlineDate.value = props.editor.values.target_online_date ? new Date(props.editor.values.target_online_date) : null
    members.value = props.editor.values.members.length
        ? props.editor.values.members.map((member) => ({ ...member }))
        : [{ user_id: null, role: null }]
    visible.value = true
}

defineExpose({ open })

function save() {
    const isMembers = kind.value === "members"
    const target = isMembers ? props.editor.routes.members : props.editor.routes.update
    const payload = isMembers
        ? { members: members.value.filter((member) => member.user_id && member.role) }
        : kind.value === "markets"
            ? { markets: markets.value }
            : { target_landing_date: toIso(landingDate.value), target_online_date: toIso(onlineDate.value) }

    router.patch(route(target.name, target.parameters), payload, {
        preserveScroll: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
        onSuccess: () => visible.value = false,
    })
}
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="titles[kind]" :style="{ width: kind === 'members' ? '44rem' : '26rem' }" :breakpoints="{ '640px': '94vw' }">
        <div class="space-y-3 text-sm">
            <ProductDevelopmentMarketSelect v-if="kind === 'markets'" v-model="markets" :options="editor.options.markets" :placeholder="ctrans('Select markets')" />

            <template v-else-if="kind === 'dates'">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target landing") }}</label>
                    <DatePicker v-model="landingDate" size="small" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target online") }}</label>
                    <DatePicker v-model="onlineDate" size="small" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" :minDate="landingDate ?? undefined" :invalid="!!errors.target_online_date" />
                    <p v-if="errors.target_online_date" class="mt-1 text-xs text-red-600">{{ errors.target_online_date }}</p>
                </div>
            </template>

            <template v-else>
                <p class="text-xs text-gray-500">{{ ctrans("One person can hold several roles: add a row for each role") }}</p>
                <div v-for="(member, index) in members" :key="index" class="space-y-1.5">
                <div class="grid grid-cols-[11rem_minmax(0,1fr)_auto] items-center gap-2">
                        <ProductDevelopmentRoleSelect v-model="member.role" :options="editor.options.roles as any" size="small" :placeholder="ctrans('Role')" @update:modelValue="onRoleChange(member)" />
                        <ProductDevelopmentUserSelect v-model="member.user_id" :options="peopleFor(member.role) as any" :disabled="!peopleFor(member.role).length" size="small" :placeholder="personPlaceholder(member.role)" />
                        <Button type="tertiary" size="xs" :tooltip="ctrans('Remove')" @click="members.splice(index, 1)">
                            <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <ProductDevelopmentMemberAccessWarning :userId="member.user_id" :role="member.role" />
                </div>
                <p v-if="Object.keys(errors).length" class="text-xs text-red-600">{{ Object.values(errors)[0] }}</p>
                <Button type="tertiary" size="xs" :label="ctrans('Add member')" @click="members.push({ user_id: null, role: null })">
                    <template #icon><FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </div>

        <template #footer>
            <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="visible = false">
                <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
            </Button>
            <Button type="primary" size="xs" icon="fas fa-save" :label="ctrans('Save')" :loading="saving" @click="save" />
        </template>
    </Dialog>
</template>
