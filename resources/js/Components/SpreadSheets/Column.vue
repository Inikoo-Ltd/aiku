<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 10:16:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Popper from "vue3-popper";
import SettingColumns from './SettingColumns.vue';
import { faEllipsisV } from '@far';
import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
library.add(faEllipsisV);

const props = defineProps({
    onCopyAll: {
        type: Function,
        required: true
    },
    onCopyRow: {
        type: Function,
        required: true
    },
    updateDataAndSetFocus: {
        type: Function,
        required: true
    },
    onCopyAllEmpty:{
        type: Function,
        required: true
    },
    column: Object,
    colIndex: Number,
    rowIndex: Number,
    setData: Array,
    row: Object,
    isDisable:Boolean,
});

const emits = defineEmits(['copyAll', 'copyRow', 'updateDataAndSetFocus']);

const onCopyAll = (column) => {
    emits('copyAll', column);
};

const onCopyAllEmpty = (column) => {
  props.onCopyAllEmpty(column);
};

const onCopyRow = (position) => {
    props.onCopyRow(position)
};

const updateDataAndSetFocus = () => {
    emits('updateDataAndSetFocus');
};



</script>

<template>
    <div class="flex">
        <div class="w-11/12" v-if="column.readonly">{{ row[column.prop] }}</div>
        <div class="w-11/12" v-if="column.columnType === 'string'">
            <input type="text" v-model="row[column.prop]" class="input" @blur="updateDataAndSetFocus"
                :disabled="isDisable" />
        </div>
        <div class="w-11/12" v-if="column.columnType === 'select'">
            <select v-model="row[column.prop]" class="input" @blur="updateDataAndSetFocus"  :disabled="isDisable">
                <option v-for="option in column.options" :value="option.label" :key="option.label">
                    {{ option.label }}
                </option>
            </select>
        </div>
        <div class="w-1/12 flex justify-center items-center">
            <Popper v-if="!column.readonly" arrow class="w-full border-0">
                <template #content>
                    <SettingColumns :onCopyAll="onCopyAll" :column="{ rowIndex, colIndex, column }"
                        :lengthData="setData.length" :onCopyAllEmpty="onCopyAllEmpty" :onCopyRow ="onCopyRow" />
                </template>
              <div class="setting" >
                <font-awesome-icon :icon="['far', 'ellipsis-v']" fixed-width />
              </div>
            </Popper>
        </div>
    </div>
</template>

<style>
.setting {
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
