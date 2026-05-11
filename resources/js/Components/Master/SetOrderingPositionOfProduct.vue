<script setup lang="ts">
import { ref, nextTick, onMounted, onBeforeUnmount, computed, watch } from "vue"
import { Head } from "@inertiajs/vue3"
import draggable from "vuedraggable"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "../Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import { faCheck, faTimes } from "@fas";
import { isEqual } from 'lodash-es'
import { faTrash } from "@far";

const props = withDefaults(
    defineProps<{
        data: any
        useDelete?: boolean
        disable?: boolean
    }>(),
    {
        disable : false,
        useDelete: false
    }
)

const viewMode = ref<'list' | 'card'>('list')
const items = ref(
    (props.data?.data ?? []).map((item: any, index: number) => ({
        ...item,
        order: index + 1,
    }))
)
console.log("Items:", items.value)

const emits = defineEmits([
    "update:data",
    "delete"
])



const sortBy = ref<'name' | 'code'>('name')
const sortDirection = ref<'asc' | 'desc'>('asc')
const history = ref<any[][]>([])
const future = ref<any[][]>([])
const clone = (data: any) => JSON.parse(JSON.stringify(data))

const saveHistory = () => {
    history.value.push(clone(items.value))
    if (history.value.length > 50) history.value.shift()
    future.value = []
}

const undo = () => {
    if (!history.value.length) return
    const prev = history.value.pop()
    future.value.push(clone(items.value))
    items.value = prev
}

const redo = () => {
    if (!future.value.length) return
    const next = future.value.pop()
    history.value.push(clone(items.value))
    items.value = next
}

const updateOrder = () => {
    saveHistory()
    items.value = items.value.map((item, index) => ({
        ...item,
        order: index + 1,
        index_under_family: index
    }))


    console.log("Updated order:", items.value)
    emits("update:data", items.value);
}

const editingId = ref<number | null>(null)
const tempIndex = ref<number | null>(null)

const startEditIndex = async (item: any, index: number) => {
    editingId.value = item.id
    tempIndex.value = index + 1

    await nextTick()
    document.querySelector<HTMLInputElement>(".index-input")?.focus()
}

const removeItem = (item: any) => {
    saveHistory()

    items.value = items.value.filter(i => i.id !== item.id)

    updateOrder()

    emits("delete", item)
}


const applyNewIndex = (item: any) => {
    if (!tempIndex.value) return

    saveHistory()

    let newIndex = tempIndex.value - 1
    newIndex = Math.max(0, Math.min(newIndex, items.value.length - 1))

    const oldIndex = items.value.findIndex(i => i.id === item.id)
    if (oldIndex === -1) return

    const movedItem = items.value.splice(oldIndex, 1)[0]
    items.value.splice(newIndex, 0, movedItem)

    updateOrder()

    editingId.value = null
    tempIndex.value = null
}


const handleKey = (e: KeyboardEvent) => {
    if (editingId.value !== null) return

    if (e.ctrlKey && e.key === "z") {
        e.preventDefault()
        undo()
    }

    if (e.ctrlKey && (e.key === "y" || (e.shiftKey && e.key === "Z"))) {
        e.preventDefault()
        redo()
    }
}

const applySort = (type: 'manual' | 'name' | 'code') => {
    // toggle direction if same type
    if (sortBy.value === type) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = type
        sortDirection.value = 'asc'
    }

    if (type === 'manual') return

    saveHistory()

    const dir = sortDirection.value === 'asc' ? 1 : -1

    items.value.sort((a, b) => {
        const aVal = (a[type] || '').toString()
        const bVal = (b[type] || '').toString()
        return aVal.localeCompare(bVal) * dir
    })

    updateOrder()
}

const getArrow = (type: 'name' | 'code') => {
    if (sortBy.value !== type) return ''
    return sortDirection.value === 'asc' ? '↑' : '↓'
}


watch(
    () => props.data?.data,
    (newData) => {
        if (!newData) return

        if (isEqual(newData, items.value)) return

        items.value = newData.map((item: any, index: number) => ({
            ...item,
            order: index + 1,
        }))
    },
    { immediate: true }
)


onMounted(() => window.addEventListener("keydown", handleKey))
onBeforeUnmount(() => window.removeEventListener("keydown", handleKey))
</script>
<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">
                <div class="inline-flex rounded-lg border bg-gray-100 p-1">
                    <button @click="applySort('name')"
                        class="px-3 py-1.5 text-xs rounded-md transition flex items-center gap-1" :class="sortBy === 'name'
                            ? 'bg-white shadow text-gray-900'
                            : 'text-gray-500 hover:text-gray-700'">
                        Name
                        <span class="text-[10px]">{{ getArrow('name') }}</span>
                    </button>

                    <button @click="applySort('code')"
                        class="px-3 py-1.5 text-xs rounded-md transition flex items-center gap-1" :class="sortBy === 'code'
                            ? 'bg-white shadow text-gray-900'
                            : 'text-gray-500 hover:text-gray-700'">
                        Code
                        <span class="text-[10px]">{{ getArrow('code') }}</span>
                    </button>
                </div>
            </div>

            <div class="flex">
                <slot name="before-button-list"></slot>
                <div class="inline-flex rounded-lg border bg-gray-100 p-1">
                    <button @click="viewMode = 'list'"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs rounded-md transition" :class="viewMode === 'list'
                            ? 'bg-white shadow text-gray-900 buttonPrimary'
                            : 'text-gray-500 hover:text-gray-700'">
                        ≡ List
                    </button>

                    <button @click="viewMode = 'card'"
                        class="flex items-center gap-1 px-3 py-1.5 text-xs rounded-md transition" :class="viewMode === 'card'
                            ? 'bg-white shadow text-gray-900 buttonPrimary'
                            : 'text-gray-500 hover:text-gray-700'">
                        ▦ Card
                    </button>
                </div>
                <slot name="after-button-list>"></slot>
            </div>

        </div>

        <!-- EMPTY STATE -->
        <template v-if="!items.length">
            <slot name="empty">
                <div class="text-center py-10 text-gray-400 border rounded bg-white">
                    {{ trans('No products found.') }}
                </div>
            </slot>
        </template>

        <!-- LIST -->
        <draggable v-else-if="viewMode === 'list'" :disable="disable" v-model="items" item-key="id" handle=".drag-handle"
            @end="updateOrder" animation="200" ghost-class="drag-ghost" chosen-class="drag-chosen"
            drag-class="drag-dragging" class="space-y-2">
            <template #item="{ element, index }">
                <div class="flex items-center gap-3 p-2 border rounded bg-white hover:bg-gray-50">
                    <div class="drag-handle cursor-move text-gray-400" v-if="!disable">☰</div>

                    <div class="w-10 text-xs text-gray-400">
                        <input v-if="editingId === element.id" v-model.number="tempIndex" type="number" :min="1"
                            :max="items.length" class="index-input w-full border rounded px-1 text-xs"
                            @blur="applyNewIndex(element)" @keyup.enter="applyNewIndex(element)" />

                        <span v-else @dblclick="startEditIndex(element, index)">
                            {{ index + 1 }}
                        </span>
                    </div>

                    <slot name="list-content" :item="element">
                        <slot name="image-list" :item="element">
                            <Image :src="element.image_thumbnail?.main?.original" class="w-10 h-10 object-cover rounded" />
                        </slot>
                        

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate">
                                {{ element.code }}
                            </div>

                            <div class="text-xs text-gray-400 truncate">
                                {{ element.name }}
                            </div>
                        </div>
                    </slot>

                    <Button v-if="useDelete && !disable" type="negative" size="xs" @click="removeItem(element)" :icon="faTrash">
                    </Button>
                </div>
            </template>
        </draggable>

        <!-- CARD -->
        <draggable v-else :disable="disable" v-model="items" item-key="id" handle=".drag-handle" @end="updateOrder" animation="200"
            ghost-class="drag-ghost" chosen-class="drag-chosen" drag-class="drag-dragging" tag="div"
            class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <template #item="{ element, index }">
                <div class="border rounded p-2 bg-white hover:shadow-sm">
                    <div class="flex justify-between">
                        <div class=" flex items-center gap-2 text-xs text-gray-400 mb-1">
                            <span v-if="!disable" class="drag-handle">☰</span>

                            <div class="w-10 flex items-center">
                                <input v-if="editingId === element.id" v-model.number="tempIndex" type="number" :min="1"
                                    :max="items.length" class="index-input w-full border rounded px-1 text-xs"
                                    @blur="applyNewIndex(element)" @keyup.enter="applyNewIndex(element)" />

                                <span v-else class="cursor-pointer" @dblclick="startEditIndex(element, index)">
                                    {{ index + 1 }}
                                </span>
                            </div>
                        </div>
                        <Button v-if="useDelete && !disable" type="negative" size="xs" @click="removeItem(element)" :icon="faTrash">

                        </Button>
                    </div>


                    <slot name="card-content" :item="element">

                        <slot name="image-card" :item="element">
                             <Image :src="element.image_thumbnail?.main?.original"
                            class="w-full h-24 object-cover rounded mb-2" />
                        </slot>
                     

                        <div class="text-sm font-medium line-clamp-2">
                            {{ element.name }}
                        </div>

                        <div class="text-xs text-gray-400">
                            {{ element.code }}
                        </div>

                    </slot>
                </div>
            </template>
        </draggable>
    </div>
</template>

<style>
.drag-dragging {
    opacity: 0.9;
    transform: rotate(2deg) scale(1.03);
    z-index: 50;
}

.drag-ghost {
    opacity: 0.3;
    background: #e5e7eb;
    border: 2px dashed #9ca3af;
    border-radius: 8px;
}

.drag-chosen {
    background: #f9fafb;
}

.sortable-ghost {
    min-height: 60px;
}

.buttonPrimary {
    background-color: var(--theme-color-4) !important;
    color: var(--theme-color-5) !important;
    border: 1px solid color-mix(in srgb, var(--theme-color-4) 80%, black);

    &:hover {
        background-color: color-mix(in srgb, var(--theme-color-4) 85%, black) !important;
    }

    &:focus {
        box-shadow: 0 0 0 2px var(--theme-color-4) !important;
    }

    &:disabled {
        background-color: color-mix(in srgb, var(--theme-color-4) 70%, grey) !important;
    }
}
</style>