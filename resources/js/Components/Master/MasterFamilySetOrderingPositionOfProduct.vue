<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import draggable from "vuedraggable"
import Button from "@/Components/Elements/Buttons/Button.vue";

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

// update order after drag
const updateOrder = () => {
  items.value = items.value.map((item, index) => ({
    ...item,
    index_under_master_family: index,
  }))

  router.patch(route('grp.models.master_product_category.reorder_index', {
    masterProductCategory: route().params['masterFamily']
  }), {
    indexing: items.value
  });

  // optional: send to backend
  // router.post('/update-order', {
  //   items: items.value.map(i => ({
  //     id: i.id,
  //     order: i.order
  //   }))
  // })
}
</script>

<template>
  <Head title="Products" />

  <div class="p-4">

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex gap-2">
        <Button
          :key="viewMode"
          @click="viewMode = 'list'"
          :type="viewMode == 'list' ? 'primary' : 'gray'"
        >
          List
        </Button>

        <Button
          :key="viewMode"
          @click="viewMode = 'card'"
          :type="viewMode == 'card' ? 'primary' : 'gray'"
        >
          Card
        </Button>
      </div>
    </div>

    <!-- LIST VIEW -->
    <draggable
      v-if="viewMode === 'list'"
      v-model="items"
      item-key="id"
      handle=".drag-handle"
      @end="updateOrder"
      animation="200"
      ghost-class="drag-ghost"
      chosen-class="drag-chosen"
      drag-class="drag-dragging"
      class="space-y-2"
    >
      <template #item="{ element, index }">
        <div class="flex items-center gap-3 p-2 border rounded bg-white hover:bg-gray-50 transition">

          <!-- drag -->
          <div class="drag-handle cursor-move text-gray-400">
            ☰
          </div>

          <!-- index -->
          <div class="text-xs text-gray-400 w-6">
            {{ index + 1 }}
          </div>

          <!-- image -->
          <img
            :src="element.image_thumbnail?.main?.original?.webp"
            class="w-10 h-10 object-cover rounded"
          />

          <!-- info -->
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate">
              {{ element.name }}
            </div>
            <div class="text-xs text-gray-400 truncate">
              {{ element.code }}
            </div>
          </div>

          <!-- price -->
          <div class="text-sm font-medium">
            £{{ element.price }}
          </div>
        </div>
      </template>
    </draggable>

    <!-- CARD VIEW -->
    <draggable
      v-else
      v-model="items"
      item-key="id"
      handle=".drag-handle"
      @end="updateOrder"
      animation="200"
      ghost-class="drag-ghost"
      chosen-class="drag-chosen"
      drag-class="drag-dragging"
      tag="div"
      class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3"
    >
      <template #item="{ element, index }">
        <div class="border rounded p-2 bg-white hover:shadow-sm transition">

          <!-- drag -->
          <div class="drag-handle cursor-move text-xs text-gray-400 mb-1">
            ☰ Drag
          </div>

          <!-- image -->
          <img
            :src="element.image_thumbnail?.main?.original?.webp"
            class="w-full h-24 object-cover rounded mb-2"
          />

          <!-- index -->
          <div class="text-xs text-gray-400 mb-1">
            #{{ index + 1 }}
          </div>

          <!-- name -->
          <div class="text-sm font-medium line-clamp-2">
            {{ element.name }}
          </div>

          <!-- code -->
          <div class="text-xs text-gray-400">
            {{ element.code }}
          </div>

          <!-- price -->
          <div class="text-sm font-semibold mt-1">
            £{{ element.price }}
          </div>
        </div>
      </template>
    </draggable>

  </div>
</template>

<style>
/* item yang lagi di drag */
.drag-dragging {
  opacity: 0.9;
  transform: rotate(2deg) scale(1.03);
  z-index: 50;
}

/* placeholder / tempat drop */
.drag-ghost {
  opacity: 0.3;
  background: #e5e7eb;
  border: 2px dashed #9ca3af;
  border-radius: 8px;
}

/* item yang dipilih */
.drag-chosen {
  background: #f9fafb;
}

/* efek tambahan biar slot keliatan */
.sortable-ghost {
  min-height: 60px;
}
</style>