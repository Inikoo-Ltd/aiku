<script setup lang="ts">
import { ref, nextTick, onMounted, onBeforeUnmount } from "vue"
import { Head } from "@inertiajs/vue3"
import draggable from "vuedraggable"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "../Image.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import { faCheck, faTimes } from "@fas";

const props = defineProps<{
    data: any
}>()

const viewMode = ref<'list' | 'card'>('list')
const items = ref(
    (props.data?.data ?? []).map((item: any, index: number) => ({
        ...item,
        order: index + 1,
    }))
)

const emits = defineEmits()

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
        index_under_master_family: index
    }))

    console.log("New order:", items.value)

    emits("update:data", items.value);

    /*       router.patch(route('grp.models.master_product_category.reorder_index', {
        masterProductCategory: route().params['masterFamily']
      }), {
        indexing: items.value
      }); */
}

const editingId = ref<number | null>(null)
const tempIndex = ref<number | null>(null)

const startEditIndex = async (item: any, index: number) => {
    editingId.value = item.id
    tempIndex.value = index + 1

    await nextTick()
    document.querySelector<HTMLInputElement>(".index-input")?.focus()
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

onMounted(() => window.addEventListener("keydown", handleKey))
onBeforeUnmount(() => window.removeEventListener("keydown", handleKey))
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div class="inline-flex rounded-lg border bg-gray-100 p-1">
                        <button @click="applySort('name')"
                            class="px-3 py-1.5 text-xs rounded-md transition flex items-center gap-1" :class="sortBy === 'name'
                                ? 'bg-white shadow text-gray-900'
                                : 'text-gray-500 hover:text-gray-700'">
                            Name
                            <span class="text-[10px]">
                                {{ getArrow('name') }}
                            </span>
                        </button>

                        <button @click="applySort('code')"
                            class="px-3 py-1.5 text-xs rounded-md transition flex items-center gap-1" :class="sortBy === 'code'
                                ? 'bg-white shadow text-gray-900'
                                : 'text-gray-500 hover:text-gray-700'">
                            Code
                            <span class="text-[10px]">
                                {{ getArrow('code') }}
                            </span>
                        </button>

                    </div>

                </div>
            </div>
            <div class="inline-flex rounded-lg border bg-gray-100 p-1">
                <button @click="viewMode = 'list'"
                    class="flex items-center gap-1 px-3 py-1.5 text-xs rounded-md transition" :class="viewMode === 'list'
                        ? 'bg-white shadow text-gray-900'
                        : 'text-gray-500 hover:text-gray-700'">
                    <span>≡</span>
                    <span>List</span>
                </button>
                <button @click="viewMode = 'card'"
                    class="flex items-center gap-1 px-3 py-1.5 text-xs rounded-md transition" :class="viewMode === 'card'
                        ? 'bg-white shadow text-gray-900'
                        : 'text-gray-500 hover:text-gray-700'">
                    <span>▦</span>
                    <span>Card</span>
                </button>

            </div>
        </div>

        <draggable v-if="viewMode === 'list'" v-model="items" item-key="id" handle=".drag-handle" @end="updateOrder"
            animation="200" ghost-class="drag-ghost" chosen-class="drag-chosen" drag-class="drag-dragging"
            class="space-y-2">
            <template #item="{ element, index }">
                <div class="flex items-center gap-3 p-2 border rounded bg-white hover:bg-gray-50">

                    <div class="drag-handle cursor-move text-gray-400">☰</div>

                    <div class="w-10 text-xs text-gray-400">
                        <input v-if="editingId === element.id" v-model.number="tempIndex" type="number" :min="1"
                            :max="items.length" @input="tempIndex = Math.max(1, Math.min(tempIndex || 1, items.length))"
                            class="index-input w-full border rounded px-1 text-xs" @blur="applyNewIndex(element)"
                            @keyup.enter="applyNewIndex(element)" />

                        <span v-else @dblclick="startEditIndex(element, index)"
                            class="cursor-pointer hover:text-gray-700">
                            {{ index + 1 }}
                        </span>
                    </div>

                    <Image :src="element.image_thumbnail?.main.original" class="w-10 h-10 object-cover rounded" />

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">
                            {{ element.name }}
                        </div>
                        <div class="flex gap-4 text-xs text-gray-400 truncate">


                            <div class="flex gap-4">
                                <FontAwesomeIcon v-if="element.status" :icon="faCheck" :class="'text-green-500'"
                                    v-tooltip="trans('Active')" />
                                <FontAwesomeIcon v-else :icon="faTimes" :class="'text-red-500'"
                                    v-tooltip="trans('Inactive')" />

                                {{ element.code }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>

        <!-- CARD -->
        <draggable v-else v-model="items" item-key="id" handle=".drag-handle" @end="updateOrder" animation="200"
            ghost-class="drag-ghost" chosen-class="drag-chosen" drag-class="drag-dragging" tag="div"
            class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <template #item="{ element, index }">
                <div class="border rounded p-2 bg-white hover:shadow-sm">

                    <div class="drag-handle cursor-move text-xs text-gray-400 mb-1">
                        ☰ Drag
                    </div>

                    <Image :src="element.image_thumbnail?.main?.original"
                        class="w-full h-24 object-cover rounded mb-2 flex justify-center" />

                    <div class="text-xs text-gray-400 mb-1">
                        <input v-if="editingId === element.id" v-model.number="tempIndex" type="number" :min="1"
                            :max="items.length" @input="tempIndex = Math.max(1, Math.min(tempIndex || 1, items.length))"
                            class="index-input w-12 border rounded px-1 text-xs" @blur="applyNewIndex(element)"
                            @keyup.enter="applyNewIndex(element)" />

                        <span v-else @dblclick="startEditIndex(element, index)"
                            class="cursor-pointer hover:text-gray-700">
                            #{{ index + 1 }}
                        </span>
                    </div>

                    <div class="text-sm font-medium line-clamp-2">
                        {{ element.name }}
                    </div>

                    <div class="text-xs text-gray-400">
                        <div class="flex gap-4">
                            <FontAwesomeIcon v-if="element.status" :icon="faCheck" :class="'text-green-500'"
                                v-tooltip="trans('Active')" />
                            <FontAwesomeIcon v-else :icon="faTimes" :class="'text-red-500'"
                                v-tooltip="trans('Inactive')" />

                            {{ element.code }}
                        </div>
                    </div>
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
</style>