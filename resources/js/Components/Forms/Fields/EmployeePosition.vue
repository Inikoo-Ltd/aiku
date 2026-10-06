<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAd, faEye, faBullhorn, faCashRegister, faChessQueen, faCube, faStore, faInfoCircle, faCircle, faCrown, faBars, faAbacus, faCheckDouble, faQuestionCircle, faTimes, faCheckCircle as falCheckCircle } from "@fal"
import { faBoxUsd, faHelmetBattle, faExclamationCircle, faCheckCircle as fasCheckCircle, faCrown as fasCrown } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { get, set } from "lodash-es"
import { ctrans } from "@/Composables/useTrans"
import { useForm } from "@inertiajs/vue3"
import { routeType } from "@/types/route"
import { notify } from "@kyvg/vue3-notification"


library.add(faAd, faEye, faBoxUsd, faHelmetBattle, faChessQueen, faCube, faStore, faCashRegister, faBullhorn, faInfoCircle, faCircle, faCrown, faBars, faAbacus, faCheckDouble, faQuestionCircle, faTimes, faExclamationCircle, fasCheckCircle, falCheckCircle, fasCrown)

interface TypeShop {
    id: number;
    slug: string;
    code: string;
    name: string;
    short_name?: string | null;
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
        isDetailsHidden?: boolean
        subDepartment: {
            slug: string
            label: string
            grade?: string
            optionsType?: string[]
            number_employees: number
            isHide?: boolean
            isIndependent?: boolean
        }[]
        options?: TypeShop[] | TypeWarehouse[]
        optionsSlug?: string[]
        optionsClosed?: TypeShop[] | TypeWarehouse[]
        optionsType?: string
        value?: any
    };
}

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
}>()


const employeePositionForm = {
    [props.fieldName]: props.form?.organisations?.[props.fieldName] || props.form?.[props.fieldName] || "fffff"
}
const newForm = props.saveButton ? useForm(employeePositionForm || {}) : reactive(props.form)
const onSubmitNewForm = () => {
    newForm
        .transform((data) => ({
            permissions: data[props.fieldName]
        }))
        .submit(
            props.fieldData.updateJobPositionsRoute.method || "patch",
            route(props.fieldData.updateJobPositionsRoute.name, {
                ...props.fieldData.updateJobPositionsRoute.parameters,
                organisation: props.fieldData.is_in_organisation ? undefined : props.organisationId
            }),
            {
                preserveScroll: true,
                onSuccess: () => notify({
                    title: ctrans("Success"),
                    text: ctrans("Successfully update the permissions"),
                    type: "success"
                }),
                onError: () => notify({
                    title: ctrans("Something went wrong"),
                    text: ctrans("Failed to update the permissions"),
                    type: "error"
                })
            }
        )
}


const selectableShopStates = ["open", "in_process"]
const isShopSelectable = (shop: { state: string }) => selectableShopStates.includes(shop.state)

const optionsList = {
    shops: props.options.shops.data?.filter(shop => isShopSelectable(shop)),
    fulfilments: props.options.fulfilments?.data || [],
    warehouses: props.options.warehouses?.data || [],
    positions: props.options.positions?.data || [],
    productions: props.options.productions?.data || []
}

const shopsLength = optionsList.shops?.length
const fulfilmentsLength = optionsList.fulfilments?.length
const warehousesLength = optionsList.warehouses?.length
const productionsLength = optionsList.productions?.length

const optionsJob = reactive<optionsJob>({
    org_admin: {
        key: "org_admin",
        department: ctrans("Org admin"),
        icon: "fal fa-crown",
        subDepartment: [
            {
                slug: "org-admin",
                label: ctrans("Organisation Administrator"),
                number_employees: props.options.positions.data.find(position => position.slug == "org-admin")?.number_employees || 0
            }
        ],
        isHide: !props.options.positions.data.some(position => position.code == "org-admin")
        // value: null
    },

    agt: {
        key: "agt",
        department: ctrans("Agent"),
        icon: "fal fa-box-usd",
        subDepartment: [
            {
                slug: "agt-m",
                grade: "manager",
                label: ctrans("Manager"),
                number_employees: props.options.positions.data.find(position => position.code == "agt-m")?.number_employees || 0
            },
            {
                slug: "agt-c",
                grade: "clerk",
                label: ctrans("Clerk"),
                number_employees: props.options.positions.data.find(position => position.code == "agt-c")?.number_employees || 0
            }
        ],
        isHide: !props.options.positions.data.some(position => ["agt-m", "agt-c"].includes(position.code))
    },

    hr: {
        key: "hr",
        department: ctrans("Human Resources"),
        icon: "fal fa-user-hard-hat",
        subDepartment: [
            {
                slug: "hr-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                number_employees: props.options.positions.data.find(position => position.slug == "hr-m")?.number_employees || 0
            },
            {
                slug: "hr-c",
                grade: "clerk",
                label: ctrans("Worker"),
                number_employees: props.options.positions.data.find(position => position.slug == "hr-c")?.number_employees || 0
            },
            {
                slug: "hr-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                number_employees: props.options.positions.data.find(position => position.slug == "hr-v")?.number_employees || 0
            }
        ]
        // value: null
    },

    acc: {
        key: "acc",
        department: ctrans("Accounting"),
        icon: "fal fa-abacus",
        subDepartment: [
            {
                slug: "acc-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                number_employees: props.options.positions.data.find(position => position.slug == "acc-m")?.number_employees || 0
            },
            {
                slug: "acc-c",
                grade: "clerk",
                label: ctrans("Worker"),
                number_employees: props.options.positions.data.find(position => position.slug == "acc-c")?.number_employees || 0
            },
            {
                slug: "acc-o",
                grade: "orders",
                isIndependent: true,
                label: ctrans("Create orders"),
                optionsType: ["shops"],
                isHide: shopsLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "acc-o")?.number_employees || 0
            },
            {
                slug: "acc-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                number_employees: props.options.positions.data.find(position => position.slug == "acc-v")?.number_employees || 0
            }
        ],
        isDetailsHidden: true
        // value: null
    },

    shop_viewer: {
        key: "shop_viewer",
        department: ctrans("Viewer"),
        icon: "fal fa-eye",
        scope: "shop",
        subDepartment: [
            {
                slug: "cus-v",
                grade: "staff",
                label: ctrans("Viewer"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "cus-v")?.number_employees || 0
            }
        ],
        isHide: shopsLength < 1
    },

    shop_admin: {
        key: "shop_admin",
        department: ctrans("Shop admin"),
        icon: "fal fa-chess-queen",
        scope: "shop",
        subDepartment: [
            {
                slug: "shop-admin",
                label: ctrans("Shop Administrator"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "shop_admin")?.number_employees || 0
            }
        ],
        isHide: shopsLength < 1
        // value: null
    },

    shk: {
        key: "shk",
        department: ctrans("Catalogue/Web"),
        icon: "fal fa-store",
        departmentRightIcons: ["fal fa-cube", "fal fa-globe"],
        scope: "shop",
        subDepartment: [
            {
                slug: "shk-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "shk-m")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => !isShopSelectable(job)),
        optionsSlug: props.options.shops.data?.filter(job => isShopSelectable(job)).map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },

    mrk: {
        key: "mrk",
        department: ctrans("Marketing/Offers"),
        icon: "fal fa-bullhorn",
        scope: "shop",
        subDepartment: [
            {
                slug: "mrk-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "mrk-m")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => !isShopSelectable(job)),
        optionsSlug: props.options.shops.data?.filter(job => isShopSelectable(job)).map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },
    shop_ppc: {
        key: "ppc",
        department: ctrans("PPC"),
        icon: "fal fa-ad",
        scope: "shop",
        subDepartment: [
            {
                slug: "ppc-shop",
                grade: "clerk",
                label: ctrans("PPC"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "shop-ppc")?.number_employees || 0
            }

        ]
    },

    cus: {
        key: "cus",
        department: ctrans("CRM/Chat"),
        departmentRightIcons: ["fal fa-user", "fal fa-route"],
        icon: "fal fa-user",
        scope: "shop",
        subDepartment: [
            {
                slug: "cus-c",
                grade: "clerk",
                label: ctrans("Agent"),
                optionsType: ["shops"],
                number_employees: props.options.positions.data.find(position => position.slug == "cus-c")?.number_employees || 0
            }
        ],
        optionsClosed: props.options.shops.data?.filter(job => !isShopSelectable(job)),
        optionsSlug: props.options.shops.data?.filter(job => isShopSelectable(job)).map(job => job.slug),
        isHide: shopsLength < 1
        // value: null
    },

    buy: {
        key: "buy",
        department: ctrans("Buyer"),
        icon: "fal fa-box-usd",
        subDepartment: [
            {
                slug: "buy",
                grade: "buyer",
                label: ctrans("Buyer"),
                number_employees: props.options.positions.data.find(position => position.slug == "buy")?.number_employees || 0
            },
            {
                slug: "buy-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                number_employees: props.options.positions.data.find(position => position.slug == "buy-v")?.number_employees || 0
            }
        ]
        // value: null
    },

    wah: {
        key: "wah",
        department: ctrans("Warehouse"),
        icon: "fal fa-inventory",
        subDepartment: [
            {
                slug: "wah-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "wah-m")?.number_employees || 0
            },
            {
                slug: "wah-sc",
                grade: "clerk",
                label: ctrans("Stock Controller"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "wah-sc")?.number_employees || 0
            },
            {
                slug: "wah-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "wah-v")?.number_employees || 0
            }
        ],
        isHide: warehousesLength < 1
        // value: null
    },

    gi: {
        key: "gi",
        department: ctrans("Goods in"),
        icon: "fal fa-arrow-to-bottom",
        subDepartment: [
            {
                slug: "gi-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "gi-m")?.number_employees || 0
            },
            {
                slug: "gi-c",
                grade: "clerk",
                label: ctrans("Worker"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "gi-c")?.number_employees || 0
            },
            {
                slug: "gi-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "gi-v")?.number_employees || 0
            }
        ],
        isHide: warehousesLength < 1
    },

    dist: {
        key: "dist",
        department: ctrans("Goods out"),
        icon: "fal fa-arrow-from-left",
        subDepartment: [
            {
                slug: "dist-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-m")?.number_employees || 0
            },
            {
                slug: "dist-pik",
                grade: "clerk",
                label: ctrans("Picker/Returns"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-pik")?.number_employees || 0
            },
            {
                slug: "dist-excp-pick",
                grade: "clerk",
                label: ctrans("Replenisher"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-excp-pick")?.number_employees || 0
            },
            {
                slug: "dist-pak",
                grade: "clerk",
                label: ctrans("Packer"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-pak")?.number_employees || 0
            },
            {
                slug: "dist-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                optionsType: ["warehouses"],
                number_employees: props.options.positions.data.find(position => position.slug == "dist-v")?.number_employees || 0
            }
        ],
        isHide: warehousesLength < 1
        // value: null
    },

    prod: {
        key: "prod",
        department: ctrans("Production"),
        icon: "fal fa-industry",
        subDepartment: [
            {
                slug: "prod-m",
                grade: "manager",
                label: ctrans("Floor supervisor"),
                optionsType: ["productions"],
                number_employees: props.options.positions.data.find(position => position.slug == "prod-m")?.number_employees || 0
            },
            {
                slug: "prod-p",
                grade: "clerk",
                label: ctrans("Mix preparer"),
                optionsType: ["productions"],
                number_employees: props.options.positions.data.find(position => position.slug == "prod-p")?.number_employees || 0
            },
            {
                slug: "prod-d",
                grade: "clerk",
                label: ctrans("Foreman"),
                optionsType: ["productions"],
                number_employees: props.options.positions.data.find(position => position.slug == "prod-d")?.number_employees || 0
            },
            {
                slug: "prod-c",
                grade: "clerk",
                label: ctrans("Operative"),
                optionsType: ["productions"],
                number_employees: props.options.positions.data.find(position => position.slug == "prod-c")?.number_employees || 0
            },
            {
                slug: "prod-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                optionsType: ["productions"],
                number_employees: props.options.positions.data.find(position => position.slug == "prod-v")?.number_employees || 0
            }
        ],
        isHide: productionsLength < 1
        // value: null
    },

    ful: {
        key: "ful",
        department: ctrans("Fulfilment"),
        icon: "fal fa-hand-holding-box",
        subDepartment: [
            {
                slug: "ful-m",
                grade: "manager",
                label: ctrans("Supervisor"),
                optionsType: ["fulfilments", "warehouses"],
                isHide: (warehousesLength < 1 || fulfilmentsLength < 1),
                number_employees: props.options.positions.data.find(position => position.slug == "cus-m")?.number_employees || 0
            },
            {
                slug: "ful-wc",
                grade: "clerk",
                label: ctrans("Warehouse Clerk"),
                optionsType: ["warehouses"],
                isHide: warehousesLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "ful-wc")?.number_employees || 0
            },
            {
                slug: "ful-c",
                grade: "clerk",
                label: ctrans("Office Clerk"),
                optionsType: ["fulfilments"],
                isHide: fulfilmentsLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "ful-c")?.number_employees || 0
            },
            {
                slug: "ful-v",
                grade: "viewer",
                label: ctrans("Viewer"),
                optionsType: ["fulfilments"],
                isHide: fulfilmentsLength < 1,
                number_employees: props.options.positions.data.find(position => position.slug == "ful-v")?.number_employees || 0
            }
        ],
        optionsSlug: props.options.warehouses.data.map(job => job.slug),
        isHide: (warehousesLength < 1 || fulfilmentsLength < 1)
        // value: null
    }
})


// console.log('options Job', props.options.warehouses.data)
// Temporary data
const openFineTune = ref("")

// When the radio is clicked
const handleClickSubDepartment = (department: string, subDepartmentSlug: any, optionType: string[]) => {
    // ('mrk', 'mrk-c', ['shops', 'fulfilment'])

    // If click on the active subDepartment, then unselect it
    if (newForm?.[props.fieldName]?.[subDepartmentSlug]) {
        delete newForm[props.fieldName][subDepartmentSlug]
    } else {
        for (const key in newForm[props.fieldName]) {
            // key == wah-m || mrk-c || hr-c
            // Check if the 'wah-m' contain the substring 'wah'
            if (optionsJob[department].subDepartment.some(sub => sub.slug == key)) {
                const existingSubDepartment = optionsJob[department].subDepartment.find(sub => sub.slug == key)
                const clickedSubDepartment = optionsJob[department].subDepartment.find(sub => sub.slug == subDepartmentSlug)
                if (existingSubDepartment?.isIndependent || clickedSubDepartment?.isIndependent) {
                    continue
                }
                if (existingSubDepartment?.grade != clickedSubDepartment?.grade) {
                    // Delete mrk-c
                    delete newForm[props.fieldName][key]
                }
            }
        }

        // If department have 'options' (i.e. web, wah, cus)
        if (optionType?.some(option => optionsList[option])) {
            // newForm[props.fieldName][subDepartmentSlug] = {}  // declare empty object so able to put new key
            set(newForm, [props.fieldName, subDepartmentSlug], {})
            for (const type in optionType) {
                // type == 'fulfilment' | 'warehouse' | 'shop'
                newForm[props.fieldName][subDepartmentSlug][optionType[type]] = optionsList[optionType[type]].map(xxx => xxx.slug)
            }
        } else {
            // If department is simple department (have no shops/warehouses)
            set(newForm, [props.fieldName, subDepartmentSlug], [])
        }
    }

    if (newForm?.errors?.[props.fieldName]) {
        newForm.errors[props.fieldName] = ""
    }
}

// Method: on clicked radio inside 'Advanced selection'
const onClickJobFineTune = (departmentName: string, shopSlug: string, subDepartmentSlug: any, optionType: string) => {
    // ('mrk', 'mrk-c', ['shops', 'fulfilment'])

    // If 'uk' is exist in mrk-m then delete it
    if (get(newForm[props.fieldName], [subDepartmentSlug, optionType], []).includes(shopSlug)) {
        if (newForm[props.fieldName][subDepartmentSlug][optionType].length === 1) {
            // if mrk-m.shops: ['uk'] (only 1 length), then delete mrk-m
            delete newForm[props.fieldName][subDepartmentSlug][optionType]

            if (!Object.keys(newForm[props.fieldName][subDepartmentSlug] || {}).length) {
                // if mrk-o: {}, then delete mrk-o
                delete newForm[props.fieldName][subDepartmentSlug]
            }
        } else {
            // if mrk-m.shops: ['uk', 'ed'] (more than 1 length), then delete
            const indexShopName = get(newForm[props.fieldName], [subDepartmentSlug, optionType], []).indexOf(shopSlug)
            if (indexShopName !== -1) {
                newForm[props.fieldName][subDepartmentSlug][optionType].splice(indexShopName, 1)
            }
        }
    } else {
        for (const key in newForm[props.fieldName]) {
            // // key == wah-m || mrk-c || hr-c
            // if wah-m include wah
            if (optionsJob[departmentName].subDepartment.some(sub => sub.slug == key)) {

                // If other subDepartment's grade is not equal as selected subDepartment's grade
                if (optionsJob[departmentName].subDepartment.find(sub => sub.slug == key)?.grade != optionsJob[departmentName].subDepartment.find(sub => sub.slug == subDepartmentSlug)?.grade) {

                    // Check if wah-c include 'uk'
                    const indexShopName = get(newForm[props.fieldName], [key, optionType], []).indexOf(shopSlug)
                    if (indexShopName !== -1) {
                        // if wah-c: ['uk'] then delete 'uk'
                        newForm[props.fieldName][key][optionType].splice(indexShopName, 1)

                        // if wah-c: [] then delete wah-c
                        if (!newForm[props.fieldName][key][optionType].length) {
                            delete newForm[props.fieldName][key]
                        }
                    }
                }
            }
        }

        // if mrk-m already exist, then push 'ed'
        if (get(newForm[props.fieldName], [subDepartmentSlug, optionType], false)) {
            newForm[props.fieldName][subDepartmentSlug][optionType].push(shopSlug)
        }

        // if mrk-m not exist then create array ['uk']
        else {
            newForm[props.fieldName][subDepartmentSlug] = {
                ...newForm[props.fieldName][subDepartmentSlug],
                [optionType]: [shopSlug]
            }
        }
    }

    if (newForm?.errors?.[props.fieldName]) {
        newForm.errors[props.fieldName] = ""
    }
}

const isLevelGroupAdmin = (jobGroupLevel?: string) => {
    if (!jobGroupLevel) {
        return false
    }
    return ["group_admin", "group_sysadmin", "group_procurement"].includes(jobGroupLevel)
}

const isRadioChecked = (subDepartmentSlug: string) => {
    return Object.keys(newForm[props.fieldName] || {}).includes(subDepartmentSlug)
}

const isMounted = ref(false)

onMounted(() => {
    setTimeout(() => {
        isMounted.value = true
    }, 300)
})

const emits = defineEmits<{
    (e: "countPosition", value: number): void
}>()
watch(() => newForm, () => {
    const xxx = Object.keys(newForm[props.fieldName]).length
    // console.log('newForm', xxx)
    emits("countPosition", xxx)
}, { deep: true, immediate: true })


const isGradeAllCheckedInCurrentAndInShopAdmin = (subDepartmentSlug: string) => {
    
    const selectedGradeInShop = get(newForm, [props.fieldName, subDepartmentSlug, 'shops'], [])  // ["uk"]

    if (selectedGradeInShop.length) {
        // [...["awd"], ...["uk"]]
        const combineSelectionInCurrentAndShopAdminSlug = [...get(newForm, [props.fieldName, 'shop-admin', 'shops'], []), ...selectedGradeInShop]
        
        return optionsList.shops.every((shop) => combineSelectionInCurrentAndShopAdminSlug.includes(shop.slug))
    } else {
        return false
    }
}

const shopScopeDepartments = computed(() =>
    Object.entries(optionsJob)
        .filter(([, jobGroup]) => jobGroup.scope === "shop" && !jobGroup.isHide)
        .map(([departmentName, jobGroup]) => ({ departmentName, jobGroup }))
)

const sharedShopNamePrefix = (() => {
    const prefixCounts = new Map<string, number>()
    for (const shop of optionsList.shops) {
        const prefix = shop.name.split(" ").slice(0, 2).join(" ") + " "
        prefixCounts.set(prefix, (prefixCounts.get(prefix) || 0) + 1)
    }
    const [prefix, count] = [...prefixCounts.entries()].sort((a, b) => b[1] - a[1])[0] || ["", 0]
    return count >= 3 ? prefix : ""
})()

const shopShortName = (shop: TypeShop) =>
    shop.short_name || (sharedShopNamePrefix && shop.name.startsWith(sharedShopNamePrefix) ? shop.name.slice(sharedShopNamePrefix.length) : shop.name)

const isShopPermissionImplied = (shopSlug: string, subDepartmentSlug: string) =>
    !!props.isGroupAdminSelected
    || isRadioChecked("org-admin")
    || isRadioChecked("group-admin")
    || (subDepartmentSlug !== "shop-admin" && get(newForm, [props.fieldName, "shop-admin", "shops"], []).includes(shopSlug))

const isShopPermissionChecked = (shopSlug: string, subDepartmentSlug: string) =>
    get(newForm, [props.fieldName, subDepartmentSlug, "shops"], []).includes(shopSlug)


const isSomeShopCheckedInSameGrade = (subDepartmentSlug: string) => {
    const selectedShopInThisGrade = get(newForm, [props.fieldName, subDepartmentSlug, 'shops'], [])  // ["uk"]
    if (selectedShopInThisGrade.length) {
        return optionsList.shops.some((shop) => selectedShopInThisGrade.includes(shop.slug))
    } else {
        return false
    }

}
</script>

<template>
    <div class="relative">
        <!-- authorised fulfilment: {{ fulfilmentsLength }} <br> authorised shop: {{ shopsLength }} <br> authorised warehouse: {{ warehousesLength }} <br> authorised production: {{ productionsLength }} -->
        <div class="flex gap-x-2">
            <div class="w-full relative flex flex-col text-xs divide-y-[1px]">
                <template v-if="isMounted">
                    <template v-for="(jobGroup, departmentName, idxJobGroup) in optionsJob" :key="`${departmentName}${idxJobGroup}`">
                            <div v-if="!jobGroup.isHide && jobGroup.scope !== 'shop'"
                                 class="grid grid-cols-3 gap-x-1.5 px-2 items-center even:bg-gray-50 transition-all duration-200 ease-in-out">
                                <!-- Section: Department label -->
                                <div class="flex items-center gap-x-1.5 py-2">
                                    <FontAwesomeIcon v-if="jobGroup.icon" :icon="jobGroup.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                                    {{ jobGroup.department }}
                                </div>

                                <!-- Section: Radio (the clickable area) -->
                                <div class="h-full col-span-2 flex-col transition-all duration-200 ease-in-out">
                                    <div class="flex items-center divide-x divide-slate-300">
                                        <!-- Button: Radio position -->
                                        <div class="pl-2 flex items-center gap-x-4">
                                            <template v-for="subDepartment, idxSubDepartment in jobGroup.subDepartment">
                                                <!-- If subDepartment is have at least 1 Fulfilment, or have at least 1 Shop, or have at least 1 Warehouse, or have at least 1 Production, or is a simple sub department (i.e buyer, administrator, etc) -->
                                                <button
                                                    v-if="!subDepartment.isHide"
                                                    @click.prevent="handleClickSubDepartment(departmentName, subDepartment.slug, subDepartment.optionsType)"
                                                    class="group h-full cursor-pointer flex items-center justify-start rounded-md py-3 px-3 font-medium disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                                                    :class="(isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level)) || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') ? 'text-green-500' : ''"
                                                    :disabled="
                                                        isGroupAdminSelected
                                                        || (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level))
                                                        || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin')
                                                        || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin')
                                                            ? true
                                                            : false
                                                    "
                                                >

                                                    <div class="relative text-left">
                                                        <div class="absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2">
                                                            <template
                                                                v-if="isGroupAdminSelected || (isRadioChecked('org-admin') && subDepartment.slug != 'org-admin' && !isLevelGroupAdmin(jobGroup.level)) || (isRadioChecked('group-admin') && subDepartment.slug != 'group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDepartment.slug !== 'shop-admin')">
                                                                <FontAwesomeIcon v-if="(idxSubDepartment === 0 && optionsList.shops.every((shop) => get(newForm, [props.fieldName, 'shop-admin', 'shops'], []).includes(shop.slug))) || isGradeAllCheckedInCurrentAndInShopAdmin(subDepartment.slug)" icon="fas fa-check-circle" class="" fixed-width aria-hidden="true" />
                                                                <FontAwesomeIcon v-else-if="idxSubDepartment === 0 || isSomeShopCheckedInSameGrade(subDepartment.slug)" icon="fal fa-check-circle" class="xtext-green-500" fixed-width aria-hidden="true" />
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
                                        <div v-if="!jobGroup.isDetailsHidden && jobGroup.subDepartment.some(subDep => subDep.optionsType?.some(option => optionsList[option]?.length > 1))" class="flex gap-x-2 px-3">
                                            <button @click.prevent="() => openFineTune = openFineTune === jobGroup.key ? '' : jobGroup.key"
                                                    class="underline disabled:no-underline whitespace-nowrap cursor-pointer disabled:cursor-auto disabled:text-gray-400"
                                            >
                                                {{ ctrans("Show details") }}
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Section: Advanced selection -->
                                    <Transition mode="in-out">
                                        <div v-if="openFineTune === jobGroup.key" class="relative bg-slate-400/10 border border-gray-300 rounded-md py-2 px-2 mb-3">
                                            <div class="flex gap-x-8 mb-3">
                                                <div class="flex flex-col gap-y-4 pt-4">
                                                    <template v-for="optionData, optionKey, optionIdx in optionsList" :key="optionKey + optionIdx">
                                                        <div v-if="jobGroup.subDepartment.some(subDep => subDep.optionsType?.includes(optionKey))" class="">
                                                            <div class="text-white grid" 
                                                                :style="{
                                                                    'grid-template-columns': `repeat(${1 + jobGroup.subDepartment.length}, minmax(0, 1fr))`
                                                                }"
                                                            >
                                                                <div class="capitalize">
                                                                    <!-- {{ optionKey }} -->
                                                                </div>
                                                                <div v-for="jobLabel in jobGroup.subDepartment.map(subDep => subDep.label)" class="py-0.5 bg-gray-400 text-center">
                                                                    {{ jobLabel }}  <!-- Supervisor/Worker -->
                                                                </div>
                                                            </div>
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
                                                                                @click.prevent="onClickJobFineTune(departmentName, shop.slug, subDep.slug, optionKey)"
                                                                                class="group h-full cursor-pointer flex items-center justify-center rounded-md px-3 font-medium disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                                                                                :disabled="isGroupAdminSelected || isRadioChecked('org-admin') || isRadioChecked('group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDep.slug !== 'shop-admin' && get(newForm, [props.fieldName, 'shop-admin', optionKey], []).includes(shop.slug))"
                                                                                v-tooltip="subDep.label"
                                                                            >
                                                                                <div class="relative text-left">
                                                                                    <template
                                                                                        v-if="isRadioChecked('org-admin') || isRadioChecked('group-admin') || (isRadioChecked('shop-admin') && jobGroup.scope === 'shop' && subDep.slug !== 'shop-admin' && get(newForm, [props.fieldName, 'shop-admin', optionKey], []).includes(shop.slug))">
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
                                            <div @click="openFineTune = ''" class="absolute top-1 right-2 w-fit px-1 text-slate-400 hover:text-slate-500 cursor-pointer hover:">
                                                <FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                    </template>

                    <div v-if="shopsLength && shopScopeDepartments.length" class="mt-2 pt-2 border-t border-gray-300 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-gray-500">
                                    <th rowspan="2" class="text-left font-medium px-2 py-1 align-bottom">{{ ctrans("Shop") }}</th>
                                    <th v-for="{ departmentName, jobGroup } in shopScopeDepartments" :key="departmentName"
                                        :colspan="jobGroup.subDepartment.length"
                                        :rowspan="jobGroup.subDepartment.length === 1 ? 2 : 1"
                                        class="px-2 pt-1 font-medium text-gray-700 border-l border-gray-200 whitespace-nowrap"
                                    >
                                        <FontAwesomeIcon v-if="jobGroup.icon" :icon="jobGroup.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                                        {{ jobGroup.department }}
                                    </th>
                                </tr>
                                <tr>
                                    <template v-for="{ departmentName, jobGroup } in shopScopeDepartments.filter(({ jobGroup }) => jobGroup.subDepartment.length > 1)" :key="departmentName">
                                        <th v-for="(subDepartment, idxSubDepartment) in jobGroup.subDepartment" :key="subDepartment.slug"
                                            class="px-1 pb-1 font-normal"
                                            :class="idxSubDepartment === 0 ? 'border-l border-gray-200' : ''"
                                        >
                                            <button
                                                @click.prevent="handleClickSubDepartment(departmentName, subDepartment.slug, subDepartment.optionsType)"
                                                :disabled="isGroupAdminSelected || isRadioChecked('org-admin') || isRadioChecked('group-admin')"
                                                v-tooltip="ctrans('Toggle for all shops')"
                                                class="whitespace-nowrap underline decoration-dotted text-gray-600 hover:text-gray-900 disabled:no-underline disabled:text-gray-400"
                                            >
                                                {{ subDepartment.label }}
                                            </button>
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="shop in optionsList.shops" :key="shop.slug" class="even:bg-gray-50">
                                    <td class="px-2 py-1.5 whitespace-nowrap">
                                        <span v-tooltip="`${shop.code} · ${shop.name}`">{{ shopShortName(shop) }}</span>
                                    </td>
                                    <template v-for="{ departmentName, jobGroup } in shopScopeDepartments" :key="departmentName">
                                        <td v-for="(subDepartment, idxSubDepartment) in jobGroup.subDepartment" :key="subDepartment.slug"
                                            class="text-center"
                                            :class="idxSubDepartment === 0 ? 'border-l border-gray-200' : ''"
                                        >
                                            <button
                                                @click.prevent="onClickJobFineTune(departmentName, shop.slug, subDepartment.slug, 'shops')"
                                                :disabled="isShopPermissionImplied(shop.slug, subDepartment.slug)"
                                                v-tooltip="`${jobGroup.department}: ${subDepartment.label}`"
                                                class="px-2 py-1 disabled:cursor-not-allowed"
                                            >
                                                <template v-if="isShopPermissionImplied(shop.slug, subDepartment.slug)">
                                                    <FontAwesomeIcon v-if="idxSubDepartment === 0" icon="fas fa-check-circle" class="text-gray-400" fixed-width aria-hidden="true" />
                                                    <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-300" fixed-width aria-hidden="true" />
                                                </template>
                                                <FontAwesomeIcon v-else-if="isShopPermissionChecked(shop.slug, subDepartment.slug)" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                                                <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-400 hover:text-gray-700" fixed-width aria-hidden="true" />
                                            </button>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <div v-if="saveButton" class="mt-2 mr-2">
                <div v-if="newForm.processing" class="h-9 align-bottom text-center">
                    <FontAwesomeIcon icon="fad fa-spinner-third" class="text-2xl animate-spin" fixed-width aria-hidden="true" />
                </div>
                <div v-else-if="newForm.isDirty" @click="() => onSubmitNewForm()" class="h-9 align-bottom text-center cursor-pointer">
                    <FontAwesomeIcon icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" fixed-width aria-hidden="true" />
                </div>
                <div v-else class="h-9 align-bottom text-center">
                    <FontAwesomeIcon icon="fal fa-save" class="h-8 text-gray-300" fixed-width aria-hidden="true" />
                </div>
            </div>


        </div>

        <!-- State: error icon & error description -->
        <Transition name="spin-to-down">
            <FontAwesomeIcon v-if="newForm.errors?.[fieldName]" icon="fas fa-exclamation-circle" class="absolute top-0 right-5 h-6 w-6 text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-else-if="newForm.recentlySuccessful" icon="fas fa-check-circle" class="absolute top-0 right-5 h-6 w-6 text-green-500" fixed-width aria-hidden="true" />
        </Transition>

        <div v-if="newForm.errors?.[fieldName]" class="mt-1 flex items-center gap-x-1.5 pointer-events-none">
            <p class="text-sm text-red-500 italic">*{{ newForm.errors[fieldName] }}</p>
        </div>

    </div>


</template>
