<script setup lang="ts">
import { ref, computed, watch, nextTick } from "vue"
import axios from "axios"
import Popover from "primevue/popover"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCommentAltLines, faSearch, faTrashAlt, faSave, faTimes, faCheck, faUser, faUsers, faSpinner } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { stripPictureTokens } from "@/Composables/useWhatsappMarkup"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"

interface SavedReply {
    id: number
    title: string
    body: string
    is_personal: boolean
    shop_id: number | null
    can_manage: boolean
}

type Visibility = "personal" | "shop" | "organisation"

const props = defineProps<{
    organisation: string
    shopId?: number | null
    composerText?: string | null
}>()

const emit = defineEmits<{
    (e: "insert", body: string): void
}>()

const popover = ref<InstanceType<typeof Popover> | null>(null)
const searchInput = ref<InstanceType<typeof InputText> | null>(null)
const replies = ref<SavedReply[]>([])
const canShare = ref(false)
const canShareAcrossShops = ref(false)
const loadedForShopId = ref<number | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)
const search = ref("")

const isSaving = ref(false)
const isSaveFormOpen = ref(false)
const newTitle = ref("")
const visibility = ref<Visibility>("personal")
const saveError = ref<string | null>(null)

const confirmingDeleteId = ref<number | null>(null)
const deletingId = ref<number | null>(null)
const deleteError = ref<string | null>(null)

const textToSave = computed(() => stripPictureTokens(props.composerText ?? "").trim())

const visibilityOptions = computed(() => [
    { value: "personal" as const, label: ctrans("Only me"), icon: faUser },
    { value: "shop" as const, label: ctrans("Team, this shop"), icon: faUsers },
    ...(canShareAcrossShops.value ? [{ value: "organisation" as const, label: ctrans("Team, every shop"), icon: faUsers }] : []),
])

const filteredReplies = computed(() => {
    const term = search.value.trim().toLowerCase()

    return term
        ? replies.value.filter((reply) => reply.title.toLowerCase().includes(term) || reply.body.toLowerCase().includes(term))
        : replies.value
})

const groups = computed(() => [
    { key: "personal", label: ctrans("Personal"), replies: filteredReplies.value.filter((reply) => reply.is_personal) },
    { key: "shared", label: ctrans("Shared"), replies: filteredReplies.value.filter((reply) => !reply.is_personal) },
].filter((group) => group.replies.length))

const errorMessage = (error: any, fallback: string): string =>
    error?.response?.data?.message || fallback

const load = async () => {
    if (!props.shopId) {
        return
    }

    isLoading.value = true
    loadError.value = null

    try {
        const { data } = await axios.get(route("grp.org.chat.agents.saved_replies.index", [props.organisation]), {
            params: { shop_id: props.shopId },
        })
        replies.value = data.data ?? []
        canShare.value = !!data.can_share
        canShareAcrossShops.value = !!data.can_share_across_shops
        loadedForShopId.value = props.shopId
    } catch (error: any) {
        loadError.value = errorMessage(error, ctrans("Saved replies could not be loaded"))
    } finally {
        isLoading.value = false
    }
}

watch(() => props.shopId, () => {
    loadedForShopId.value = null
    replies.value = []
})

const toggle = (event: Event) => {
    popover.value?.toggle(event)
}

const onShow = async () => {
    search.value = ""
    confirmingDeleteId.value = null
    deleteError.value = null

    if (loadedForShopId.value !== props.shopId) {
        await load()
    }

    await nextTick()
    ;(searchInput.value as any)?.$el?.focus()
}

const onHide = () => {
    isSaveFormOpen.value = false
    saveError.value = null
}

const pick = (reply: SavedReply) => {
    emit("insert", reply.body)
    popover.value?.hide()
}

const openSaveForm = () => {
    newTitle.value = textToSave.value.split("\n")[0].slice(0, 60)
    visibility.value = "personal"
    saveError.value = null
    isSaveFormOpen.value = true
}

const save = async () => {
    if (!props.shopId || !newTitle.value.trim() || !textToSave.value) {
        return
    }

    isSaving.value = true
    saveError.value = null

    try {
        const { data } = await axios.post(route("grp.org.chat.agents.saved_replies.store", [props.organisation]), {
            shop_id: props.shopId,
            title: newTitle.value.trim(),
            body: textToSave.value,
            is_shared: visibility.value !== "personal",
            across_shops: visibility.value === "organisation",
        })
        replies.value = [...replies.value, data.data].sort((first, second) => first.title.localeCompare(second.title))
        isSaveFormOpen.value = false
    } catch (error: any) {
        saveError.value = errorMessage(error, ctrans("The reply could not be saved"))
    } finally {
        isSaving.value = false
    }
}

const remove = async (reply: SavedReply) => {
    if (confirmingDeleteId.value !== reply.id) {
        confirmingDeleteId.value = reply.id
        deleteError.value = null
        return
    }

    deletingId.value = reply.id
    deleteError.value = null

    try {
        await axios.delete(route("grp.org.chat.agents.saved_replies.destroy", [props.organisation, reply.id]))
        replies.value = replies.value.filter((item) => item.id !== reply.id)
        confirmingDeleteId.value = null
    } catch (error: any) {
        deleteError.value = errorMessage(error, ctrans("The reply could not be deleted"))
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <div class="contents">
        <button type="button" @click="toggle" :disabled="!shopId"
            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40"
            v-tooltip="ctrans('Saved replies')" :aria-label="ctrans('Saved replies')">
            <FontAwesomeIcon :icon="faCommentAltLines" class="text-sm" fixed-width aria-hidden="true" />
        </button>

        <Popover ref="popover" @show="onShow" @hide="onHide">
            <div class="flex w-[22rem] max-w-[calc(100vw-2rem)] flex-col gap-2">
                <div class="relative">
                    <FontAwesomeIcon :icon="faSearch" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400" fixed-width aria-hidden="true" />
                    <InputText ref="searchInput" v-model="search" size="small" class="w-full !pl-8"
                        :placeholder="ctrans('Search saved replies')" :aria-label="ctrans('Search saved replies')" />
                </div>

                <div class="max-h-72 overflow-y-auto">
                    <div v-if="isLoading" class="flex items-center gap-2 px-1 py-3 text-xs text-gray-500">
                        <FontAwesomeIcon :icon="faSpinner" spin fixed-width aria-hidden="true" />
                        {{ ctrans("Loading") }}
                    </div>
                    <p v-else-if="loadError" class="px-1 py-2 text-xs text-red-600">{{ loadError }}</p>
                    <p v-else-if="!groups.length" class="px-1 py-3 text-xs text-gray-500">
                        {{ search ? ctrans("No saved reply matches") : ctrans("No saved replies yet. Write a reply, then save it here to use it again.") }}
                    </p>
                    <template v-else>
                        <div v-for="group in groups" :key="group.key" class="mb-1">
                            <div class="px-1 pb-0.5 pt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ group.label }}</div>
                            <div v-for="reply in group.replies" :key="reply.id"
                                class="group/reply flex items-start gap-1 rounded-md hover:bg-[--app-accent-soft]">
                                <button type="button" class="min-w-0 flex-1 px-2 py-1.5 text-left" @click="pick(reply)">
                                    <div class="truncate text-sm font-medium text-gray-700">{{ reply.title }}</div>
                                    <div class="line-clamp-2 whitespace-pre-line text-xs text-gray-500">{{ reply.body }}</div>
                                </button>
                                <button v-if="reply.can_manage" type="button" @click.stop="remove(reply)"
                                    :disabled="deletingId === reply.id"
                                    class="mt-1 mr-1 flex h-7 shrink-0 items-center gap-1 rounded px-1.5 text-xs transition-colors"
                                    :class="confirmingDeleteId === reply.id ? 'text-red-600 hover:bg-red-50' : 'text-gray-400 opacity-0 hover:bg-gray-100 hover:text-red-500 focus:opacity-100 group-hover/reply:opacity-100'"
                                    v-tooltip="confirmingDeleteId === reply.id ? undefined : ctrans('Delete')"
                                    :aria-label="ctrans('Delete saved reply')">
                                    <FontAwesomeIcon :icon="deletingId === reply.id ? faSpinner : faTrashAlt" :spin="deletingId === reply.id" fixed-width aria-hidden="true" />
                                    <span v-if="confirmingDeleteId === reply.id">{{ ctrans("Delete?") }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                    <p v-if="deleteError" class="px-1 pt-1 text-xs text-red-600">{{ deleteError }}</p>
                </div>

                <div class="border-t border-gray-100 pt-2">
                    <div v-if="isSaveFormOpen" class="flex flex-col gap-2">
                        <InputText v-model="newTitle" size="small" class="w-full" maxlength="255"
                            :placeholder="ctrans('Title')" :aria-label="ctrans('Title')" @keydown.enter.prevent="save" />
                        <SegmentedToggle v-if="canShare" v-model="visibility" :options="visibilityOptions" soft
                            :aria-label="ctrans('Who can use it')" />
                        <p v-if="saveError" class="text-xs text-red-600">{{ saveError }}</p>
                        <div class="flex justify-end gap-2">
                            <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="isSaveFormOpen = false">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Save')" :loading="isSaving"
                                :disabled="!newTitle.trim() || isSaving" @click="save">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </div>
                    <button v-else type="button" :disabled="!textToSave" @click="openSaveForm"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs text-[--app-accent] hover:bg-[--app-accent-soft] disabled:cursor-not-allowed disabled:text-gray-400 disabled:hover:bg-transparent"
                        v-tooltip="textToSave ? undefined : ctrans('Write the reply in the box first')">
                        <FontAwesomeIcon :icon="faSave" fixed-width aria-hidden="true" />
                        {{ ctrans("Save current text as a reply") }}
                    </button>
                </div>
            </div>
        </Popover>
    </div>
</template>
