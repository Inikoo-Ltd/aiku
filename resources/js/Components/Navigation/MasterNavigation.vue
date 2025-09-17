<script setup lang="ts">
import { computed } from 'vue';
import { trans } from "laravel-vue-i18n"
import ButtonWithLink from '../Elements/Buttons/ButtonWithLink.vue';
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from '@fal';

library.add(faPlus)

// Props definition
interface Props {
    isAddSubDepartment?: boolean;
    isAddFamilies?: boolean;
    isAddBoth?: boolean;
    subDepartmentRoute?: string;
    familiesRoute?: string;
}

const props = withDefaults(defineProps<Props>(), {
    isAddSubDepartment: false,
    isAddFamilies: false,
    isAddBoth: false,
    subDepartmentRoute: '',
    familiesRoute: '',
});

// Computed property untuk menentukan button mana yang ditampilkan
const buttonsToShow = computed(() => {
    const buttons = [];

    if (props.isAddBoth || props.isAddSubDepartment) {
        buttons.push({
            label: trans("Create Sub Department"),
            route_target: props.subDepartmentRoute,
            icon: faPlus,
            key: 'subDepartment'
        });
    }

    if (props.isAddBoth || props.isAddFamilies) {
        buttons.push({
            label: trans("Create Master Families"),
            route_target: props.familiesRoute,
            icon: faPlus,
            key: 'families'
        });
    }

    return buttons;
});
</script>

<template>
    <div class="border rounded-lg p-4">
        <div class="flex flex-col gap-4">
            <ButtonWithLink v-for="button in buttonsToShow" :key="button.key" :label="button.label"
                :route-target="button.route_target" :icon="button.icon" type="secondary" full />
        </div>
    </div>
</template>