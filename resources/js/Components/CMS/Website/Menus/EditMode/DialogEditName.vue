<script setup lang="ts">
import { ref, onMounted, inject } from 'vue'
import PureInput from '@/Components/Pure/PureInput.vue';
import { trans } from 'laravel-vue-i18n';
import Button from '@/Components/Elements/Buttons/Button.vue';

import { library } from '@fortawesome/fontawesome-svg-core';
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt } from '@fas';
import { faHeart } from '@far';

library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt);

const props = defineProps<{
    data_form: Object,
}>()

const emits = defineEmits<{
    (e: 'onSave', value: string): void
}>()

</script>

<template>
    <div>
        <div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans("Name") }}</div>
        <PureInput v-model="data_form.title" />
        <div class="flex justify-end mt-3">
            <Button type="save" @click="emits('onSave', data_form)" />
        </div>

    </div>

</template>
<style scss></style>
