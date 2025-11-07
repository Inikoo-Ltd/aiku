<script setup>
import { computed, ref, watch, nextTick } from "vue";
import find from "lodash-es/find";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFilter } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faFilter)

const skipUnwrap = { el: ref([]) };
let el = computed(() => skipUnwrap.el.value);

const props = defineProps({
  searchInputs: {
    type: Object,
    required: true,
  },

  forcedVisibleSearchInputs: {
    type: Array,
    required: true,
  },

  onChange: {
    type: Function,
    required: true,
  },

  onRemove: {
    type: Function,
    required: true,
  },
});

function isForcedVisible(key) {
  return props.forcedVisibleSearchInputs.includes(key);
}

watch(props.forcedVisibleSearchInputs, (inputs) => {
  const latestInput = inputs.length > 0 ? inputs[inputs.length - 1] : null;

  if (!latestInput) {
    return;
  }

  nextTick().then(() => {
    const inputElement = find(el.value, (el) => {
      return el.__vnode.key === latestInput;
    });

    if (inputElement) {
      inputElement.focus();
    }
  });
}, { immediate: true });
</script>

<template>
  <!-- A looping Field: appears if search data on table by label -->
  <div v-for="(searchInput, key) in searchInputs" v-show="searchInput.value !== null || isForcedVisible(searchInput.key)"
    :key="key" class="px-4 sm:px-0">
    <div class="flex rounded-md shadow-sm relative mt-3">
      <label :for="searchInput.key"
        class="inline-flex items-center px-4 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
        <FontAwesomeIcon icon="far fa-filter" class="h-4 w-4 mr-2 text-gray-400" aria-hidden="true" />
        <span>{{ searchInput.label }}</span></label>
      <input :id="searchInput.key" :ref="skipUnwrap.el" :key="searchInput.key" :name="searchInput.key"
        :value="searchInput.value" type="text"
        class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-r-md focus:ring-orange-500 focus:border-orange-500 text-sm border-gray-300"
        @input="onChange(searchInput.key, $event.target.value)">
      <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
        <button
          class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
          :dusk="`remove-search-row-${searchInput.key}`" @click.prevent="onRemove(searchInput.key)">
          <span class="sr-only">Remove search</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
