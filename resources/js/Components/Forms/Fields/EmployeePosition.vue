<script setup lang="ts">
import { inject, onMounted, reactive, ref, watch } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBullhorn, faCashRegister, faChessQueen, faCube, faStore, faInfoCircle, faCircle, faCrown, faBars, faAbacus, faCheckDouble, faQuestionCircle, faTimes, faCheckCircle as falCheckCircle } from "@fal";
import { faBoxUsd, faHelmetBattle, faExclamationCircle, faCheckCircle as fasCheckCircle, faCrown as fasCrown } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { get, set } from "lodash-es";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { trans } from "laravel-vue-i18n";
import { useForm } from "@inertiajs/vue3";
import { routeType } from "@/types/route";
import { notify } from "@kyvg/vue3-notification";


library.add(faBoxUsd, faHelmetBattle, faChessQueen, faCube, faStore, faCashRegister, faBullhorn, faInfoCircle, faCircle, faCrown, faBars, faAbacus, faCheckDouble, faQuestionCircle, faTimes, faExclamationCircle, fasCheckCircle, falCheckCircle, fasCrown);

interface TypeShop {
    id: number;
    slug: string;
    code: string;
    name: string;
    type: string;
    state: string;
}

interface TypeWarehouse {
    name: string;
    slug: string;
    state: string;
}

interface TypeFulfilment {
    code: string;
    id: number;
    name: string;
    sales: {};
    slug: string;
    state: string;
    type: string;
}

interface optionsJob {
    [key: string]: {
        key: string
        department: string
        departmentRightIcons?: string[]
        icon?: string
        level?: string  // group_admin || group_sysadmin || etc..
        scope?: string,  // shop
        isHide?: boolean
        subDepartment: {
            slug: string
            label: string
            grade?: string
            optionsType?: string[]
            number_employees: number
            isHide?: boolean
        }[]
        options?: TypeShop[] | TypeWarehouse[]
        optionsSlug?: string[]
        optionsClosed?: TypeShop[] | TypeWarehouse[]
        optionsType?: string
        value?: any
    };
}

const layout = inject("layout", layoutStructure);

const props = defineProps<{
    form: {
        group: string[]
        organisations: {
            [key: string]: {}
        }
    }
    fieldName: string  // organisation slug
    options: {
        positions: {
            data: {
                id: number
                slug: string
                name: string
                number_employees: number
            }[]
        }
        organisations: {}
        fulfilments: {
            data: TypeFulfilment[]
        }
        productions: {
            data: TypeFulfilment[]
        }
        shops: {
            data: TypeShop[]
        }
        warehouses: {
            data: TypeWarehouse[]
        }
    }
    fieldData: {
        list_authorised: {
            [key: string]: {
                authorised_shops: number
                authorised_fulfilments: number
                authorised_warehouses: number
                authorised_productions: number
            }
        }
        updateOrganisationPermissionsRoute: routeType
        updateEmployeeJobPositionsRoute: routeType
        updateJobPositionsRoute: routeType
        is_in_organisation: boolean
        current_organisation?: {  // the organisation of the employee
            id: number
            name: string
            slug: string
        }
    }
    saveButton?: boolean
    organisationId?: number
    isGroupAdminSelected?: boolean
}>();


const abcdef = {
    [props.fieldName]: props.form?.organisations?.[props.fieldName] || props.form?.[props.fieldName] || "fffff"
};
const newForm = props.saveButton ? useForm(abcdef || {}) : reactive(props.form);
const onSubmitNewForm = () => {

    if (props.fieldData.current_organisation?.slug == props.fieldName) {
        console.log(".");
        // If user is employeed in this organisation
        newForm
            .transform((data) => ({
                permissions: data[props.fieldName]
            }))
            .submit(
                props.fieldData.updateEmployeeJobPositionsRoute.method || "patch",
                route(props.fieldData.updateEmployeeJobPositionsRoute.name, {
                    ...props.fieldData.updateEmployeeJobPositionsRoute.parameters,
                    organisation: props.fieldData.is_in_organisation ? undefined : props.organisationId
                }),
                {
                    preserveScroll: true,
                    onSuccess: () => notify({
                        title: trans("Success"),
                        text: trans("Successfully update the permissions"),
                        type: "success"
                    }),
                    onError: () => notify({
                        title: trans("Something went wrong"),
                        text: trans("Failed to update the permissions"),
                        type: "error"
                    })
                }
            );
    } else {
        console.log(",");
        newForm
            .transform((data) => ({
                permissions: data[props.fieldName]
            }))
            .submit(
                props.fieldData.updateJobPositionsRoute.method || "patch",
                route(props.fieldData.updateJobPositionsRoute.name, { ...props.fieldData.updateJobPositionsRoute.parameters, organisation: props.fieldData.is_in_organisation ? undefined : props.organisationId }),
                {
                    preserveScroll: true,
                    onSuccess: () => notify({
                        title: trans("Success"),
                        text: trans("Successfully update the permissions"),
                        type: "success"
                    }),
                    onError: () => notify({
                        title: trans("Something went wrong"),
                        text: trans("Failed to update the permissions"),
                        type: "error"
                    })
                }
            );
    }
};


const optionsList = {
    shops: props.options.shops.data?.filter(shop => shop.state == "open"),
    fulfilments: props.options.fulfilments?.data || [],
    warehouses: props.options.warehouses?.data || [],
    positions: props.options.positions?.data || [],
    productions: props.options.productions?.data || []
};

const shopsLength = optionsList.shops?.length;
const fulfilmentsLength = optionsList.fulfilments?.length;
const warehousesLength = optionsList.warehouses?.length;
const productionsLength = optionsList.productions?.length;

const optionsJob = reactive<optionsJob>({
    org_admin: {
        key: "org_admin",
        department: trans("Org admin"),
        icon: "fal fa-crown",
        subDepartment: [
            {
                slug: "org-admin",
                label: trans("Organisation Administrator"),
                number_employees: props.options.positions.data.find(position => position.slug == "org-admin")?.number_employees || 0
            }
        ]
        // value: null
    },

    hr: {
        key: "hr",
        department: trans("Human Resources"),
        icon: "fal fa-user-hard-hat",
        subDepartment: [
            {
                slug: "hr-m",
                grade: "manager",
                label: trans("Supervisor"),
                number_employees: props.options.positions.data.find(position => position.slug == "hr-m")?.number_employees || 0
            },
            {
                slug: "hr-c",
                grade: "clerk",
                label: trans("Worker"),
                number_employees: props.options.positions.data.find(position => position.slug == "hr-c")?.number_employees || 0
            }
        ]
        // value: null
    },

    acc: {
        key: "acc",
        department: trans("Accounting"),
        icon: "fal fa-abacus",
        subDepartment: [
            {
                slug: "acc-m",
                grade: "manager",
                label: trans("Supervisor"),
                number_employees: props.options.positions.data.find(position => position.slug == "acc-m")?.number_employees || 0
            },
            {
                slug: "acc-c",
                grade: "clerk",
                label: trans("Worker"),
                number_employees: props.options.positions.data.find(position => position.slug == "acc-c")?.number_employees || 0
            }
        ]
        // value: null
    },

    shop_admin: {
        key: "shop_admin",
        department: trans("Shop admin"),
        icon: "fal fa-chess-queen",
        scope: "shop",
        subDepartment: [
            {
                slug: "shop-admin",
                label: trans("Shop Administrator"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "shop_admin")?.number_employees || 0
            }
        ],
        isHide: shopsLength < 1
        // value: null
    },
    shk: {
        key: "shk",
        department: trans("Shopkeeping"),
        icon: "fal fa-cash-register",
        departmentRightIcons: ["fal fa-cube", "fal fa-globe"],
        scope: "shop",
        subDepartment: [
            {
                slug: "shk-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "web-m")?.number_employees || 0
            },
            {
                slug: "shk-c",
                grade: "clerk",
                label: trans("Worker"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "web-c")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => job.state != "open"),
        optionsSlug: props.options.shops.data?.filter(job => job.state == "open").map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },

    mrk: {
        key: "mrk",
        department: trans("Marketing"),
        icon: "fal fa-bullhorn",
        scope: "shop",
        subDepartment: [
            {
                slug: "mrk-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "mrk-m")?.number_employees || 0
            },
            {
                slug: "mrk-c",
                grade: "clerk",
                label: trans("Worker"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "mrk-c")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => job.state != "open"),
        optionsSlug: props.options.shops.data?.filter(job => job.state == "open").map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },

    cus: {
        key: "cus",
        department: trans("Customer Service"),
        departmentRightIcons: ["fal fa-user", "fal fa-route"],
        scope: "shop",
        subDepartment: [
            {
                slug: "cus-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "cus-m")?.number_employees || 0
            },
            {
                slug: "cus-c",
                grade: "clerk",
                label: trans("Worker"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "cus-c")?.number_employees || 0
            },
            {
                slug: "cus-v",
                grade: "staff",
                label: trans("Viewer"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "cus-v")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => job.state != "open"),
        optionsSlug: props.options.shops.data?.filter(job => job.state == "open").map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },

    buy: {
        key: "buy",
        department: trans("Buyer"),
        subDepartment: [
            {
                slug: "buy",
                grade: "buyer",
                label: trans("Buyer"),
                number_employees: props.options.positions.data.find(position => position.slug == "buy")?.number_employees || 0
            }
        ]
        // value: null
    },

    wah: {
        key: "wah",
        department: trans("Warehouse"),
        subDepartment: [
            {
                slug: "wah-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "wah-m")?.number_employees || 0
            },
            {
                slug: "wah-sc",
                grade: "clerk",
                label: trans("Stock Controller"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "wah-sc")?.number_employees || 0
            }
        ],
        isHide: warehousesLength < 1
        // value: null
    },

    dist: {
        key: "dist",
        department: trans("Dispatching"),
        subDepartment: [
            {
                slug: "dist-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-m")?.number_employees || 0
            },
            {
                slug: "dist-pik",
                grade: "clerk",
                label: trans("Picker"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-pik")?.number_employees || 0
            },
            {
                slug: "dist-pak",
                grade: "clerk",
                label: trans("Packer"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-pak")?.number_employees || 0
            }
        ],
        isHide: warehousesLength < 1
        // value: null
    },

    prod: {
        key: "prod",
        department: trans("Production"),
        subDepartment: [
            {
                slug: "prod-m",
                grade: "manager",
                label: trans("Supervisor"),
                number_employees: props.options.positions.data.find(position => position.slug == "prod-m")?.number_employees || 0
            },
            {
                slug: "prod-w",
                grade: "clerk",
                label: trans("Worker"),
                number_employees: props.options.positions.data.find(position => position.slug == "prod-w")?.number_employees || 0
            }
        ],
        isHide: productionsLength < 1
        // value: null
    },

    ful: {
        key: "ful",
        department: trans("Fulfilment"),
        subDepartment: [
            {
                slug: "ful-m",
                grade: "manager",
                label: trans("Supervisor"),
                optionsType: ["fulfilments", "warehouses"],
                isHide: (warehousesLength < 1 || fulfilmentsLength < 1),
                number_employees: props.options.positions.data.find(position => position.slug == "cus-m")?.number_employees || 0
            },
            {
                slug: "ful-wc",
                grade: "clerk",
                label: trans("Warehouse Clerk"),
                optionsType: ["warehouses"],
                isHide: warehousesLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "ful-wc")?.number_employees || 0
            },
            {
                slug: "ful-c",
                grade: "clerk",
                label: trans("Office Clerk"),
                optionsType: ["fulfilments"],
                isHide: fulfilmentsLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "ful-c")?.number_employees || 0
            }
        ],
        optionsSlug: props.options.warehouses.data.map(job => job.slug),
        isHide: (warehousesLength < 1 || fulfilmentsLength < 1)
        // value: null
    }
});


// console.log('options Job', props.options.warehouses.data)
// Temporary data
const openFinetune = ref("");

// When the radio is clicked
const handleClickSubDepartment = (department: string, subDepartmentSlug: any, optionType: string[]) => {
    // ('mrk', 'mrk-c', ['shops', 'fulfilment'])

    // If click on the active subDepartment, then unselect it
    if (newForm?.[props.fieldName]?.[subDepartmentSlug]) {
        delete newForm[props.fieldName][subDepartmentSlug];
    } else {
        for (const key in newForm[props.fieldName]) {
            // key == wah-m || mrk-c || hr-c
            // Check if the 'wah-m' contain the substring 'wah'
            if (key.includes(department)) {
                // If the selected radio is not same group ('manager' group or 'clerk' group)
                if (optionsJob[department].subDepartment.find(sub => sub.slug == key)?.grade != optionsJob[department].subDepartment.find(sub => sub.slug == subDepartmentSlug)?.grade) {
                    // Delete mrk-c
                    delete newForm[props.fieldName][key];
                }
            }
        }

        // If department have 'options' (i.e. web, wah, cus)
        if (optionType?.some(option => optionsList[option])) {
            // newForm[props.fieldName][subDepartmentSlug] = {}  // declare empty object so able to put new key
            set(newForm, [props.fieldName, subDepartmentSlug], {});
            for (const type in optionType) {
                // type == 'fulfilment' | 'warehouse' | 'shop'
                newForm[props.fieldName][subDepartmentSlug][optionType[type]] = optionsList[optionType[type]].map(xxx => xxx.slug);
            }
        } else {
            // If department is simple department (have no shops/warehouses)
            set(newForm, [props.fieldName, subDepartmentSlug], []);
        }
    }

    if (newForm?.errors?.[props.fieldName]) {
        newForm.errors[props.fieldName] = "";
    }
};

// Method: on clicked radio inside 'Advanced selection'
const onClickJobFinetune = (departmentName: string, shopSlug: string, subDepartmentSlug: any, optionType: string) => {
    // ('mrk', 'mrk-c', ['shops', 'fulfilment'])

    // If 'uk' is exist in mrk-m then delete it
    if (get(newForm[props.fieldName], [subDepartmentSlug, optionType], []).includes(shopSlug)) {
        if (newForm[props.fieldName][subDepartmentSlug][optionType].length === 1) {
            // if mrk-m.shops: ['uk'] (only 1 length), then delete mrk-m
            delete newForm[props.fieldName][subDepartmentSlug][optionType];

            if (!Object.keys(newForm[props.fieldName][subDepartmentSlug] || {}).length) {
                // if mrk-o: {}, then delete mrk-o
                delete newForm[props.fieldName][subDepartmentSlug];
            }
        } else {
            // if mrk-m.shops: ['uk', 'ed'] (more than 1 length), then delete
            const indexShopName = get(newForm[props.fieldName], [subDepartmentSlug, optionType], []).indexOf(shopSlug);
            if (indexShopName !== -1) {
                newForm[props.fieldName][subDepartmentSlug][optionType].splice(indexShopName, 1);
            }
        }
    } else {
        for (const key in newForm[props.fieldName]) {
            // // key == wah-m || mrk-c || hr-c
            // if wah-m include wah
            if (key.includes(departmentName)) {

                // If other subDepartment's grade is not equal as selected subDepartment's grade
                if (optionsJob[departmentName].subDepartment.find(sub => sub.slug == key)?.grade != optionsJob[departmentName].subDepartment.find(sub => sub.slug == subDepartmentSlug)?.grade) {

                    // Check if wah-c include 'uk'
                    const indexShopName = get(newForm[props.fieldName], [key, optionType], []).indexOf(shopSlug);
                    if (indexShopName !== -1) {
                        // if wah-c: ['uk'] then delete 'uk'
                        newForm[props.fieldName][key][optionType].splice(indexShopName, 1);

                        // if wah-c: [] then delete wah-c
                        if (!newForm[props.fieldName][key][optionType].length) {
                            delete newForm[props.fieldName][key];
                        }
                    }
                } else {
                }
            }
        }

        // if mrk-m already exist, then push 'ed'
        if (get(newForm[props.fieldName], [subDepartmentSlug, optionType], false)) {
            newForm[props.fieldName][subDepartmentSlug][optionType].push(shopSlug);
        }

        // if mrk-m not exist then create array ['uk']
        else {
            newForm[props.fieldName][subDepartmentSlug] = {
                ...newForm[props.fieldName][subDepartmentSlug],
                [optionType]: [shopSlug]
            };
        }
    }

    if (newForm?.errors?.[props.fieldName]) {
        newForm.errors[props.fieldName] = "";
    }
};

const isLevelGroupAdmin = (jobGroupLevel?: string) => {
    if (!jobGroupLevel) {
        return false;
    }
    return ["group_admin", "group_sysadmin", "group_procurement"].includes(jobGroupLevel);
};

const isRadioChecked = (subDepartmentSlug: string) => {
    return Object.keys(newForm[props.fieldName] || {}).includes(subDepartmentSlug);
};

const isMounted = ref(false);

onMounted(() => {
    setTimeout(() => {
        isMounted.value = true;
    }, 300);
});

const emits = defineEmits<{
    (e: "countPosition", value: number): void
}>();
watch(() => newForm, () => {
    const xxx = Object.keys(newForm[props.fieldName]).length;
    // console.log('newForm', xxx)
    emits("countPosition", xxx);
}, { deep: true, immediate: true });


</script>

<template>
    <div class="relative">
        <!-- authorised fulfilment: {{ fulfilmentsLength }} <br> authorised shop: {{ shopsLength }} <br> authorised warehouse: {{ warehousesLength }} <br> authorised production: {{ productionsLength }} -->
        <div class="flex gap-x-2">
            <div class="w-full relative flex flex-col text-xs divide-y-[1px]">
                <template v-if="isMounted">
                    <template v-for="(jobGroup, departmentName, idxJobGroup) in optionsJob" :key="`${departmentName}${idxJobGroup}`">
                        <Teleport v-if="!jobGroup.isHide" :to="'#scopeShop' + fieldName" :disabled="jobGroup.scope !== 'shop'">
                            <div v-if="jobGroup.scope !== 'shop' && (departmentName === 'prod'  && productionsLength > 0) || departmentName !== 'prod'"
                                 class="grid grid-cols-3 gap-x-1.5 px-2 items-center even:bg-gray-50 transition-all duration-200 ease-in-out">
                                <!-- Section: Department label -->
                                <div class="flex items-center capitalize gap-x-1.5">
                                    <FontAwesomeIcon v-if="jobGroup.icon" :icon="jobGroup.icon" class="text-gray-400 fixed-width" aria-hidden="true" />
                                    {{ jobGroup.department }}
                                </div>

                                <!-- Section: Radio (the clickable area) -->
                                <div class="h-full col-span-2 flex-col transition-all duration-200 ease-in-out">
                                    <div class="flex items-center divide-x divide-slate-300">
                                        <!-- Button: Radio position -->
                                        <div class="pl-2 flex items-center gap-x-4">
                                            <template v-for="subDepartment, idxSubDepartment in jobGroup.subDepartment">
                                                <!-- If subDepartment is have atleast 1 Fulfilment, or have atleast 1 Shop, or have atleast 1 Warehouse, or have atleast 1 Production, or is a simple sub department (i.e buyer, administrator, etc) -->
                                                <button
                                                    v-if="!subDepartment.isHide"
                                                    @click.prevent="handleClickSubDepartment(departmentName, subDepartment.slug, subDepartment.optionsType)"
                                                    class="group h-full cursor-pointer flex items-center justify-start rounded-md py-3 px-3 font-medium capitalize disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                                                    :class="(isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level)) || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') ? 'text-green-500' : ''"
                                                    :disabled="
                                                        isGroupAdminSelected
                                                        || (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level))
                                                        || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin')
                                                        || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin')
                                                        ? true
                                                        : false"
                                                >
                                                    <!-- {{ (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') }} -->

                                                    <div class="relative text-left">
                                                        <div class="absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2">
                                                            <template
                                                                v-if="isGroupAdminSelected || (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level)) || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin')">
                                                                <FontAwesomeIcon v-if="idxSubDepartment === 0" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                                                                <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                                                            </template>
                                                            <template v-else-if="Object.keys(newForm[fieldName] || {}).includes(subDepartment.slug)">
                                                                <FontAwesomeIcon
                                                                    v-if="subDepartment.optionsType?.every((optionType: string) => optionsList[optionType].map((list: TypeShop | TypeFulfilment | TypeWarehouse) => list.slug).every(optionSlug => get(newForm[fieldName], [subDepartment.slug, optionType], []).includes(optionSlug)))"
                                                                    icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                                                                <FontAwesomeIcon
                                                                    v-else-if="subDepartment.optionsType?.some((optionType: string) => get(newForm[fieldName], [subDepartment.slug, optionType], []).some((optionValue: string) => optionsList[optionType].map((list: TypeShop | TypeFulfilment | TypeWarehouse) => list.slug).includes(optionValue)))"
                                                                    icon="fal fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
                                                                <FontAwesomeIcon v-else icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                                                            </template>
                                                            <FontAwesomeIcon v-else icon="fal fa-circle" fixed-width aria-hidden="true" class="text-gray-400 hover:text-gray-700" />
                                                        </div>

                                                        <span v-tooltip="subDepartment.number_employees + ' employees on this position'" :class="[
                                                            (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level)) || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin') ? 'text-gray-400' : 'text-gray-600 group-hover:text-gray-700'
                                                        ]">
                                                            {{ subDepartment.label }}
                                                            <!-- {{ subDepartment.optionsType?.every((optionType: string) => optionsList[optionType].map((list: TypeShop | TypeFulfilment | TypeWarehouse) => list.slug).every(optionSlug => get(newForm[fieldName], [subDepartment.slug, optionType], []).includes(optionSlug))) }} -->
                                                        </span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                        <!-- Button: Advanced selection -->
                                        <div v-if="jobGroup.subDepartment.some(subDep => subDep.optionsType?.some(option => optionsList[option]?.length > 1))" class="flex gap-x-2 px-3">
                                            <button @click.prevent="() => openFinetune = openFinetune === jobGroup.key ? '' : jobGroup.key"
                                                    class="underline disabled:no-underline whitespace-nowrap cursor-pointer disabled:cursor-auto disabled:text-gray-400"
                                            >
                                                {{ trans("Shops Fine tuning") }}
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Section: Advanced selection -->
                                    <Transition mode="in-out">
                                        <div v-if="openFinetune === jobGroup.key" class="relative bg-slate-400/10 border border-gray-300 rounded-md py-2 px-2 mb-3">
                                            <div class="flex gap-x-8 mb-3">
                                                <div class="flex flex-col gap-y-4 pt-4">
                                                    <template v-for="optionData, optionKey, optionIdx in optionsList" :key="optionKey + optionIdx">
                                                        <div v-if="jobGroup.subDepartment.some(subDep => subDep.optionsType?.includes(optionKey))" class="">
                                                            <div class="text-white text-center bg-gray-400 capitalize py-0.5">{{ optionKey }}</div>
                                                            <div class="flex flex-col gap-x-2 gap-y-0.5">
                                                                <!-- Section: Box radio -->
                                                                <div v-for="(shop, idxZXC) in optionData" class="grid grid-cols-4 items-center justify-start gap-x-6 min-h-6"
                                                                     :style="{
                                                                        'grid-template-columns': `repeat(${1 + jobGroup.subDepartment.length}, minmax(0, 1fr))`
                                                                    }"
                                                                >
                                                                    <!-- Section: Shop name -->
                                                                    <div class="w-40 leading-none">
                                                                        {{ shop.name }}
                                                                    </div>

                                                                    <!-- Section: Grade -->
                                                                    <template v-for="(gradeName, idxGrade) in [...new Set(jobGroup.subDepartment.map(subDepartment => subDepartment.grade))]"
                                                                              class="flex gap-x-2"
                                                                    >
                                                                        <!-- Section: Sub Department on same Grade -->
                                                                        <template v-for="subDep in jobGroup.subDepartment.filter(sub => sub.grade == gradeName)">
                                                                            <button
                                                                                v-if="subDep.optionsType?.includes(optionKey)"
                                                                                @click.prevent="onClickJobFinetune(departmentName, shop.slug, subDep.slug, optionKey)"
                                                                                class="group h-full cursor-pointer flex items-center justify-center rounded-md px-3 font-medium capitalize disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                                                                                :disabled="isGroupAdminSelected || isRadioChecked('org-admin') || isRadioChecked('group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDep.slug !== 'shop-admin')"
                                                                                v-tooltip="subDep.label"
                                                                            >
                                                                                <div class="relative text-left">
                                                                                    <template
                                                                                        v-if="isRadioChecked('org-admin') || isRadioChecked('group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDep.slug !== 'shop-admin')">
                                                                                        <FontAwesomeIcon v-if="idxGrade === 0" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                                                                                        <FontAwesomeIcon v-else icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                                                                                    </template>

                                                                                    <template v-else-if="get(newForm[fieldName], [subDep.slug, optionKey], []).includes(shop.slug)">
                                                                                        <FontAwesomeIcon v-if="Object.keys(get(newForm[fieldName], [subDep.slug, subDep.optionsType], {})).includes('org-admin')"
                                                                                                         icon="fal fa-circle" class="" fixed-width aria-hidden="true" />
                                                                                        <FontAwesomeIcon v-else icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                                                                                    </template>

                                                                                    <FontAwesomeIcon v-else icon="fal fa-circle" fixed-width aria-hidden="true" />
                                                                                </div>
                                                                            </button>
                                                                            <div v-else>
                                                                                <!-- Empty -->
                                                                            </div>
                                                                        </template>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <!-- <div v-for="subDepartment, idxSubDepartment in jobGroup.subDepartment" class="flex flex-col pl-3 first:pl-0">
                                                    <div class="text-center font-bold">{{ subDepartment.label }}</div>
                                                    <div v-for="option in subDepartment.optionsType" class="py-[2px] pl-2 rounded">
            
                                                    </div>
                                                </div> -->
                                            </div>
                                            <div v-if="jobGroup.optionsClosed?.length" class="px-2 bg-gray-400/20 py-2 rounded">
                                                <div class="flex items-center gap-x-1">
                                                    <FontAwesomeIcon icon="fal fa-info-circle" class="h-3" fixed-width aria-hidden="true" />
                                                    These {{ jobGroup.optionsType }} can't be selected due closed:
                                                </div>
                                                <div v-for="option, idxOption in jobGroup.optionsClosed" class="inline opacity-70">
                                                    <template v-if="idxOption != 0">,</template>
                                                    {{ option.name }}
                                                </div>
                                            </div>
                                            <div @click="openFinetune = ''" class="absolute top-1 right-2 w-fit px-1 text-slate-400 hover:text-slate-500 cursor-pointer hover:">
                                                <FontAwesomeIcon icon="fal fa-times" class="" aria-hidden="true" />
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                        </Teleport>
                    </template>
                </template>

                <!-- To grouping the Shope into same area -->
                <div v-if="shopsLength" :id="'scopeShop' + fieldName" class="overflow-hidden mt-2 border-t border-gray-300 ">

                </div>
            </div>

            <div v-if="saveButton" class="mt-2 mr-2">
                <div v-if="newForm.processing" class="h-9 align-bottom text-center">
                    <FontAwesomeIcon icon="fad fa-spinner-third" class="text-2xl animate-spin" fixed-width aria-hidden="true" />
                </div>
                <div v-else-if="newForm.isDirty" @click="() => onSubmitNewForm()" class="h-9 align-bottom text-center cursor-pointer">
                    <FontAwesomeIcon icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                </div>
                <div v-else class="h-9 align-bottom text-center">
                    <FontAwesomeIcon icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                </div>
            </div>


        </div>

        <!-- State: error icon & error description -->
        <Transition name="spin-to-down">
            <FontAwesomeIcon v-if="newForm.errors?.[fieldName]" icon="fas fa-exclamation-circle" class="absolute top-0 right-5 h-6 w-6 text-red-500" aria-hidden="true" />
            <FontAwesomeIcon v-else-if="newForm.recentlySuccessful" icon="fas fa-check-circle" class="absolute top-0 right-5 h-6 w-6 text-green-500" aria-hidden="true" />
        </Transition>

        <div v-if="newForm.errors?.[fieldName]" class="mt-1 flex items-center gap-x-1.5 pointer-events-none">
            <p class="text-sm text-red-500 italic">*{{ newForm.errors[fieldName] }}</p>
        </div>

    </div>


</template>
