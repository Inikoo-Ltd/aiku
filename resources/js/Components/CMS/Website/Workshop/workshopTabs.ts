import type { Component } from 'vue'
import { faThLarge, faPaintBrushAlt, faMedal } from '@fas'

import SideMenuWebsiteWorkshop from '@/Components/SideMenuWebsiteWorkshop.vue'
import SideMenuFamilyDescriptionBlockWorkshop from '@/Components/CMS/Website/FamilyDescriptionBlockWorkshop/SideMenuFamilyDescriptionBlockWorkshop.vue'
import SideMenuProductsWorkshop from '@/Components/CMS/Website/ProductsBlock/SideMenuProductsWorkshop.vue'
import SideMenuProductBlockWorkshop from '@/Components/CMS/Website/ProductBlock/SideMenuProductBlockWorkshop.vue'
import SideMenuLayoutWorkshop from '@/Components/CMS/Website/Workshop/SideMenuLayoutWorkshop.vue'

import ColorSchemeWorkshopWebsite from '@/Components/CMS/Website/Layout/ColorSchemeWorkshopWebsite.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'

export type WorkshopLayoutShape = 'single' | 'keyed' | 'blocks' | 'theme' | 'none'

export interface WorkshopPicker {
  listKey: string
  selectionKey: string
  resultKey?: string
  routeParam?: string
  title: string
  subtitle: string
  emptyTitle: string
  emptyDescription: string
  emptyAction: string
}

export interface WorkshopContext {
  data: Record<string, any>
  currency?: Record<string, any>
  layoutTheme?: Record<string, any>
  layoutState: any
  picked: Record<string, any>
  sidebarTab: number
  selectedBlock: any
  updateRoute?: Record<string, any>
}

export interface WorkshopTabConfig {
  sidebar: Component | null
  sidebarProps?: (ctx: WorkshopContext) => Record<string, any>
  preview?: Component
  previewProps?: (ctx: WorkshopContext) => Record<string, any>
  layoutShape: WorkshopLayoutShape
  picker?: WorkshopPicker
  transient?: string[]
  previewModel?: (fieldValue: Record<string, any>, ctx: WorkshopContext) => Record<string, any>
  blockProps?: (ctx: WorkshopContext) => Record<string, any>
  irisPreview?: boolean
  requiredFieldValue?: string
  screenView?: boolean
  collapsibleSidebar?: boolean
  templateRouteName?: string
  sidebarClass?: string
  previewClass?: string
}

const webBlockSidebar = (listKey: string) => (ctx: WorkshopContext) => ({
  data: ctx.layoutState,
  webBlockTypes: ctx.data.web_block_types,
  dataList: ctx.data[listKey]
})

const editableBlock = (ctx: WorkshopContext) => ({
  routeEditSubDepartment: ctx.updateRoute
})

export const WORKSHOP_TABS: Record<string, WorkshopTabConfig> = {
  website_layout: {
    sidebar: SideMenuLayoutWorkshop,
    sidebarProps: (ctx) => ({ layout: ctx.data.layout }),
    preview: ColorSchemeWorkshopWebsite,
    previewProps: (ctx) => ({ routeList: ctx.data.routeList, theme: ctx.data.layout }),
    layoutShape: 'theme',
    sidebarClass: 'col-span-12 lg:col-span-3 bg-white rounded-xl shadow-md overflow-hidden flex flex-col',
    previewClass: 'col-span-12 lg:col-span-9 bg-white rounded-xl shadow-md p-8 overflow-y-auto'
  },

  department_description: {
    sidebar: SideMenuWebsiteWorkshop,
    sidebarProps: webBlockSidebar('department'),
    layoutShape: 'keyed',
    screenView: true,
    collapsibleSidebar: true,
    blockProps: editableBlock,
    picker: {
      listKey: 'department',
      selectionKey: 'department',
      resultKey: 'sub_departments',
      routeParam: 'department',
      title: 'Department Overview',
      subtitle: 'Choose a department to preview',
      emptyTitle: 'No department selected',
      emptyDescription: 'Please pick a department to preview its data here.',
      emptyAction: 'Pick a catalouge as a data preview'
    }
  },

  sub_department: {
    sidebar: SideMenuWebsiteWorkshop,
    sidebarProps: webBlockSidebar('parent_product_category'),
    layoutShape: 'single',
    screenView: true,
    collapsibleSidebar: true,
    blockProps: editableBlock,
    picker: {
      listKey: 'parent_product_category',
      selectionKey: 'department',
      resultKey: 'sub_departments',
      routeParam: 'department',
      title: 'Department Overview',
      subtitle: 'Choose a department to preview',
      emptyTitle: 'No department selected',
      emptyDescription: 'Please pick a department to preview its data here.',
      emptyAction: 'Pick a catalouge as a data preview'
    }
  },

  families: {
    sidebar: SideMenuWebsiteWorkshop,
    sidebarProps: webBlockSidebar('parent_product_category'),
    layoutShape: 'single',
    screenView: true,
    collapsibleSidebar: true,
    blockProps: editableBlock,
    picker: {
      listKey: 'parent_product_category',
      selectionKey: 'sub_department',
      resultKey: 'families',
      routeParam: 'subDepartment',
      title: 'Sub Department Overview',
      subtitle: 'Choose a sub department to preview',
      emptyTitle: 'No sub department selected',
      emptyDescription: 'Please pick a sub department to preview its data here.',
      emptyAction: 'Pick a catalouge as a data preview'
    }
  },

  families_overview: {
    sidebar: SideMenuWebsiteWorkshop,
    sidebarProps: webBlockSidebar('parent_product_category'),
    layoutShape: 'single',
    screenView: true,
    collapsibleSidebar: true,
    blockProps: editableBlock,
    picker: {
      listKey: 'parent_product_category',
      selectionKey: 'department',
      resultKey: 'families',
      routeParam: 'department',
      title: 'Department Overview',
      subtitle: 'Choose a department to preview',
      emptyTitle: 'No department selected',
      emptyDescription: 'Please pick a department and the template to preview its data here.',
      emptyAction: 'Pick a catalouge as a data preview'
    }
  },

  families_description: {
    sidebar: SideMenuFamilyDescriptionBlockWorkshop,
    sidebarProps: (ctx) => ({
      data: ctx.layoutState,
      webBlockTypes: ctx.data.web_block_types,
      selectedBlock: ctx.selectedBlock
    }),
    layoutShape: 'blocks',
    screenView: true,
    collapsibleSidebar: true,
    templateRouteName: 'grp.json.workshop.fetch_descriptions_layout',
    blockProps: editableBlock,
    picker: {
      listKey: 'family',
      selectionKey: 'family',
      title: 'Family',
      subtitle: 'Choose a family to preview',
      emptyTitle: 'No family selected',
      emptyDescription: 'Please pick a family to preview its data here.',
      emptyAction: 'Pick a family as a data preview'
    }
  },

  products: {
    sidebar: SideMenuProductsWorkshop,
    sidebarProps: (ctx) => ({
      data: ctx.layoutState,
      webBlockTypes: ctx.data.web_block_types,
      dataList: ctx.data.families,
      selectedTab: ctx.sidebarTab,
      tabs: ctx.layoutState?.data
        ? [
            { label: 'Templates', icon: faThLarge, tooltip: 'template' },
            { label: 'Settings', icon: faPaintBrushAlt, tooltip: 'setting' },
            { label: 'Bestseller', icon: faMedal, tooltip: 'bestseller' }
          ]
        : [{ label: 'Templates', icon: faThLarge, tooltip: 'template' }]
    }),
    layoutShape: 'single',
    screenView: true,
    irisPreview: true,
    transient: ['layout', 'sub_departments'],
    previewModel: (fieldValue, ctx) => ({
      ...fieldValue,
      products: ctx.sidebarTab === 2 ? ctx.data.top_seller : ctx.data.products,
      model_type: 'family',
      model_id: ctx.data.family?.id,
      model_slug: ctx.data.family?.slug
    })
  },

  product: {
    sidebar: SideMenuProductBlockWorkshop,
    sidebarProps: (ctx) => ({
      data: ctx.layoutState,
      webBlockTypes: ctx.data.web_block_types
    }),
    layoutShape: 'single',
    screenView: true,
    irisPreview: true,
    requiredFieldValue: 'product',
    transient: ['product'],
    blockProps: (ctx) => ({
      templateEdit: 'template',
      currency: ctx.currency
    })
  },

  history: {
    sidebar: null,
    preview: TableHistories,
    previewProps: (ctx) => ({ data: ctx.data }),
    layoutShape: 'none',
    previewClass: 'col-span-12'
  }
}
