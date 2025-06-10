<script setup lang="ts">
import { ref,inject } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@fas";
import { faUnlink } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";

import Image from "@/Components/Image.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import CollectionSelector from "@/Components/Departement&Family/CollectionSelector.vue";

library.add(faImage, faUnlink);

const props = defineProps<{
  collections: {
    id: number;
    name: string;
    description?: string;
    image?: string[];
  }[];
  routeFetch: string;
  canAdd?: boolean;
  loadingUnassignIds?: number[];
  isSubmitting?: boolean;
}>();

const emit = defineEmits<{
  (e: 'assign', ids: number[]): void;
  (e: 'unassign', id: number): void;
}>();

const isModalOpen = inject('isModalOpen', ref(false));

</script>


<template>
  <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
    <div class="flex justify-between items-center mb-4">
      <div class="text-xl font-semibold text-gray-800">
        {{ trans("Collections") }}
      </div>
      <Button
        v-if="props.canAdd"
        size="xs"
        :label="trans('Add Collection')"
        type="create"
        @click="isModalOpen = true"
      />
    </div>

    <ul v-if="props.collections.length"  class="space-y-2 max-h-96 overflow-y-auto pr-2">
      <li
        v-for="collection in props.collections"
        :key="collection.id"
        class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition duration-200 p-3 flex items-start gap-3"
      >
        <!-- Thumbnail -->
        <div class="w-10 h-10 bg-gray-100 rounded-md overflow-hidden flex items-center justify-center flex-shrink-0">
          <Image
            v-if="collection.image?.[0]"
            :src="collection.image[0]"
            imageCover
            class="object-cover w-full h-full"
          />
          <FontAwesomeIcon
            v-else
            :icon="['far', 'image']"
            class="text-gray-400 w-5 h-5"
          />
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <h3 class="text-sm font-medium text-gray-800 truncate">
            {{ collection.name }}
          </h3>
          <p class="text-xs text-gray-500 line-clamp-2 mt-0.5">
            {{ collection.description || trans("No description") }}
          </p>
        </div>

        <!-- Unassign Button -->
        <Button
          type="negative"
          size="xs"
          class="ml-2"
          :label="''"
          :icon="faUnlink"
          v-tooltip="'Unassign'"
          :loading="props.loadingUnassignIds?.includes(collection.id)"
          @click="() => emit('unassign', collection.id)"
        />
      </li>
    </ul>

    <div v-else class="text-sm text-gray-500 italic mt-6">
      {{ trans("No collections found.") }}
    </div>
  </div>

  <!-- Modal -->
  <Modal
    :isOpen="isModalOpen"
    @onClose="isModalOpen = false"
    width="w-full max-w-6xl"
  >
    <CollectionSelector
      :headLabel="`${trans('Add Collection to')}`"
      :routeFetch="props.routeFetch"
      :isLoadingSubmit="props.isSubmitting"
      @submit="(ids) => emit('assign', ids)"
    />
  </Modal>
</template>

