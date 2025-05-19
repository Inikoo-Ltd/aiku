<script setup lang="ts">
import { ref } from "vue";
import Popover from "primevue/popover";
import { library, icon } from "@fortawesome/fontawesome-svg-core";
import { faGalaxy, faTimesCircle } from "@fas";
import { faBaby, faCactus, faCircle, faObjectGroup, faUser, faHouse, faTruck, faTag, faPhone, faBars } from "@fal";
import {
  faBackpack,
  faTruckLoading,
  faTruckMoving,
  faTruckContainer,
  faUser as faUserRegular,
  faWarehouse,
  faWarehouseAlt,
  faShippingFast,
  faInventory,
  faDollyFlatbedAlt,
  faBoxes,
  faShoppingCart,
  faBadgePercent,
  faChevronRight,
  faCaretRight,
  faPhoneAlt,
  faGlobe,
  faPercent,
  faPoundSign,
  faClock
} from "@far";
import { faLambda } from "@fad";

// Add icons to the library
library.add(
  faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone,
  faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory, faBars,
  faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock
);

const props = withDefaults(
  defineProps<{
    modelValue: string | SVGElement;
    iconList?: Array<string | [string, string]>;
    listType?: string;
    valueType?: string; // "fontawesome | string | svg | array"
  }>(),
  {
    iconList: [],
    valueType: "fontawesome",
    listType: "extend"
  }
);

const _popover = ref();
const allIcons = props.listType === "extend"
  ? [...[faTimesCircle, faUser, faCactus, faBaby, faObjectGroup, faGalaxy, faLambda, faBackpack, faHouse, faTruck, faTag, faPhone,
    faTruckLoading, faTruckMoving, faTruckContainer, faUserRegular, faWarehouse, faWarehouseAlt, faShippingFast, faInventory,
    faDollyFlatbedAlt, faBoxes, faShoppingCart, faBadgePercent, faChevronRight, faCaretRight, faPhoneAlt, faGlobe, faPercent, faPoundSign, faClock], ...props.iconList]
  : props.iconList;

const emits = defineEmits<{
  (e: "update:modelValue", value: string | SVGElement): void;
}>();

const toggle = (event: Event) => {
  _popover.value?.toggle(event);
};

const renderIcon = (iconData: any) => {
  if (!iconData) return icon(faCircle).html[0];

  if (typeof iconData === "string") {
    if (iconData.startsWith("<svg")) {
      return iconData; // SVG string
    } else {
      const [prefix, iconName] = iconData.split(" ");
      return icon({ prefix, iconName }).html[0]; // FontAwesome string
    }
  } else if (Array.isArray(iconData)) {
    const [prefix, iconName] = iconData;

    return icon({ prefix, iconName }).html[0];
  } else {
    return icon(iconData).html[0]; // Assume it's a FontAwesome object
  }
};

const onChangeIcon = (iconData: any) => {
  let updatedValue;

  if (props.valueType === "fontawesome") {
    updatedValue = iconData;
  } else if (props.valueType === "string") {
    if (typeof iconData === "string") {
      updatedValue = iconData;
    } else if (Array.isArray(iconData)) {
      updatedValue = iconData.join(" ");
    } else {
      updatedValue = `${iconData.prefix} ${iconData.iconName}`;
    }
  } else if (props.valueType === "svg") {
    updatedValue = icon(iconData).html[0];
  } else if (props.valueType === "array") {
    updatedValue = [iconData.prefix, iconData.iconName];
  }

  emits("update:modelValue", updatedValue);
  _popover.value?.hide();
};

defineExpose({
  allIcons,
  popover: _popover,
  modelValue: props.modelValue
});
</script>

<template>
  <span v-html="renderIcon(modelValue)" @click="toggle"></span>

  <Popover ref="_popover">
    <div class="w-full max-w-[25rem]">
      <div class="grid grid-cols-4 gap-4 h-32 overflow-y-auto">
        <div
          v-for="(iconData, index) in allIcons"
          :key="index"
          class="flex flex-col items-center justify-center p-2 rounded-lg hover:bg-gray-100 cursor-pointer transition duration-300"
          @click="() => onChangeIcon(iconData)"
        >
          <span v-html="renderIcon(iconData)" class="text-gray-700 text-lg"></span>
        </div>
      </div>
    </div>
  </Popover>
</template>

