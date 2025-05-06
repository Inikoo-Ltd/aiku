<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 30 Oct 2022 15:27:23 Greenwich Mean Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { faSpinnerThird, faSave as fadSave } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faClipboard, faPencil, faDownload, faFileExport, faPlus as falPlus, faHistory, faListAlt } from "@fal";
import { faArrowLeft, faTrashAlt, faPersonDolly, faTimes } from "@far";
import { faPlus, faSave, faUpload, faTrashUndoAlt, faThLarge, faRocket, faMinus } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { inject } from "vue";
import { Icon } from "@/types/Utils/Icon";

const layout: any = inject("layout", {});

library.add(faPlus, faSave, fadSave, faUpload, faDownload, falPlus, faRocket, faArrowLeft, faPencil, faTrashAlt, faSpinnerThird, faTrashUndoAlt, faPersonDolly, faFileExport, faClipboard, faHistory, faListAlt, faTimes, faThLarge, faMinus);


const props = withDefaults(defineProps<{
  style?: string | object
  size?: string
  icon?: Icon | string | string[]
  iconRight?: Icon | string | string[]
  action?: string
  label?: string
  full?: boolean
  capitalize?: boolean
  tooltip?: string
  loading?: boolean
  type?: string
  disabled?: boolean
  noHover?: boolean
}>(), {
  size: "m",
  capitalize: true,
  loading: false
});


let styleClass: string;
let sizeClass = "";

// Styling the Button depends on the 'style' props
if (props.type == "primary" || props.type == "create" || props.type == "save" || props.type == "upload" || props.style == "primary" || props.style == "create" || props.style == "save" || props.style == "upload") {
  styleClass = `buttonPrimary`;
}
else if (props.type == "secondary" || props.style == "secondary") { styleClass = "buttonSecondary"; }
else if (props.type == "tertiary" || props.style == "tertiary") styleClass = "bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70";
else if (props.type == "transparent" || props.style == "transparent") styleClass = "bg-transparent text-gray-700 hover:bg-gray-200/70 disabled:bg-gray-200/70";
else if (props.type == "exit" || props.style == "exit" || props.style == "exitEdit" || props.type == "edit" || props.style == "edit") styleClass = "buttonEdit";
else if (props.type == "dashed" || props.style == "dashed") styleClass = "bg-transparent border border-dashed border-gray-400 text-gray-700 hover:bg-black/10";
else if (props.type == "rainbow" || props.style == "rainbow") styleClass = "bg-indigo-500 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2";

else if (props.style == "delete" || props.style == "negative" || props.style == "cancel" || props.type == "delete" || props.type == "negative" || props.type == "cancel") styleClass = "border border-red-400 text-red-500 hover:text-red-600 hover:bg-red-100 disabled:border-red-200 disabled:bg-red-100 disabled:text-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2";
else if (props.style == "positive" || props.type == "positive") styleClass = "border border-lime-500 text-lime-600 hover:text-lime-800 hover:bg-lime-50 disabled:bg-lime-200/75 focus:outline-none focus:ring-2 focus:ring-lime-600 focus:ring-offset-2";

else if (props.style == "white" || props.type == "white") styleClass = "bg-white hover:bg-gray-300 text-gray-600";
else if (props.style == "red" || props.type == "red") styleClass = "bg-red-500 hover:bg-red-600 border border-red-500 hover:border-red-600 text-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:text-red-300 disabled:bg-red-600";
else if (props.style == "green" || props.type == "green") styleClass = `bg-green-400 ${props.noHover ? "" : "hover:bg-green-500 hover:border-green-500"}  border border-green-400  text-white focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2`;
else if (props.style == "gray" || props.type == "gray") styleClass = "bg-gray-300 hover:bg-gray-400 border border-gray-500 text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2";
else if (props.style == "black" || props.type == "black") styleClass = "bg-gray-800 hover:bg-gray-700 disabled:bg-gray-600 border border-gray-900 text-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:ring-offset-2";
else if (props.style == "indigo" || props.type == "indigo") styleClass = "bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 border border-indigo-500 text-teal-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2";
else if (props.style == "red_outline" || props.type == "red_outline") styleClass = "border border-red-400 text-red-500 hover:text-red-600 hover:bg-red-100 disabled:border-red-200 disabled:bg-red-100 disabled:text-red-300 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2";

// else if (props.style == 'negative' || props.style == 'cancel') styleClass = 'border border-red-400 text-red-600 hover:text-red-800 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2'
else if (props.style == "disabled" || props.type == "disabled") styleClass = "cursor-not-allowed border border-gray-300 bg-transparent text-gray-700 hover:bg-gray-200/70 disabled:cursor-not-allowed disabled:opacity-70";
else styleClass = `buttonPrimary`;

// Styling depends on the 'size' props
switch (props.size) {
  case "xxs":
    sizeClass = "rounded px-1 py-0.5 md:px-2 md:py-1 text-xxs";
    break;
  case "xs":
    sizeClass = "rounded px-1.5 md:px-2.5 py-1 md:py-1.5 text-xs";
    break;
  case "s":
    sizeClass = "rounded-md px-2 md:px-3 py-[5px] md:py-[7px] text-sm";
    break;
  case "m":
    sizeClass = "rounded-md px-3 md:px-4 py-[7px] md:py-[9px] text-sm";
    break;
  case "l":
    sizeClass = "rounded-md px-4 md:px-[18px] py-[9px] md:py-[11px] text-base";
    break;
  case "xl":
    sizeClass = "rounded-md px-[18px] md:px-6 py-[11px] md:py-[13px] text-lg";
    break;
}

// Auto add label for several conditions
const getActionLabel = (label: string | undefined) => {
  if (label) {
    return trans(label);
  } else {
    switch (props.style || props.type) {
      case "edit":
        return null;
      case "save":
        return trans("save");
      case "create":
        return trans("create");
      case "exit":
        return trans("exit");
      case "exitEdit":
        return trans("exit edit");
      case "cancel":
        return trans("cancel");
      case "delete":
        return trans("delete");
      case "clearMulti":
        return trans("clear");
      default:
        return "";
    }
  }
};

// Auto add icon for several conditions
const getActionIcon = (icon: any) => {
  if (icon) {
    return icon;
  } else {
    switch (props.style || props.type) {
      case "edit":
        return ["fal", "fa-pencil"];
      case "save":
        return ["fas", "fa-save"];
      case "cancel":
      case "exit":
      case "exitEdit":
        return ["far", "fa-arrow-left"];
      case "create":
        return ["fas", "fa-plus"];
      case "delete":
        return ["far", "fa-trash-alt"];
      case "withMulti":
        return ["far", "fa-border-all"];
      case "upload":
        return ["fas", "fa-upload"];
      default:
        return null;
    }
  }
};

</script>

<template>
  <button type="button"
          class="leading-4 inline-flex items-center gap-x-2 font-medium focus:outline-none disabled:cursor-not-allowed"
          :class="[
            full ? 'w-full justify-center' : 'min-w-max',
            styleClass,
            sizeClass
        ]"
          :disabled="loading || disabled || style == 'disabled' || type == 'disabled'"
          v-tooltip="tooltip ?? undefined"
  >
    <slot>
      <slot name="loading">
        <FontAwesomeIcon v-if="loading" icon="fad fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" />
      </slot>
      <slot name="icon" v-if="!loading">
        <FontAwesomeIcon v-if="getActionIcon(icon)" :icon="getActionIcon(icon)" fixed-width class="" aria-hidden="true" />
      </slot>
      <slot name="label"></slot>
      <span v-if="getActionLabel(label)" class="leading-none tabular-nums" :class="{'capitalize': capitalize}">{{ getActionLabel(label) }}</span>
      <slot name="iconRight">
        <FontAwesomeIcon v-if="iconRight" :icon="getActionIcon(iconRight)" fixed-width class="" aria-hidden="true" />
      </slot>
    </slot>
  </button>
</template>

<style lang="scss">

.buttonPrimary {
  background-color: v-bind('layout?.app?.theme[4]') !important;
  color: v-bind('layout?.app?.theme[5]') !important;
  border: v-bind('`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`');

  &:hover {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, black)`') !important;
  }

  &:focus {
    box-shadow: 0 0 0 2px v-bind('layout?.app?.theme[4]') !important;
  }

  &:disabled {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 50%, grey)`') !important;
  }
}

.buttonSecondary {
  // Primary but less opacity
  background-color: v-bind('layout?.app?.theme[4] + "22"') !important;
  border: v-bind('`1px solid ${layout?.app?.theme[4] + "88"}`');
  color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 50%, black)`') !important;

  &:hover {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4] + "22"} 90%, black)`') !important;
  }

  &:focus {
    box-shadow: 0 0 0 2px v-bind('layout?.app?.theme[4]') !important;
  }

  &:disabled {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4] + "22"} 70%, black)`') !important;
  }
}

.buttonEdit {
  // Primary but less opacity
  @apply bg-transparent text-xl !important;

  color: v-bind('layout?.app?.theme[4]') !important;

  &:hover {
    color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, ${layout?.app?.theme[5]})`') !important;
  }


  &:disabled {
    background-color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[5]} 50%, grey)`') !important;
  }
}
</style>
