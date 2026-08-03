export interface Root {
  id: number
  slug: string
  level: number
  code: string
  url: string
  type: string
  typeIcon: string[]
  is_dirty: boolean
  web_blocks_parameters: WebBlockParameters
  layout: Layout
  purpose: string
  created_at: string
  updated_at: string
  state: string
  add_web_block_route: AddWebBlockRoute
  update_model_has_web_blocks_route: UpdateModelHasWebBlocksRoute
  delete_model_has_web_blocks_route: DeleteModelHasWebBlocksRoute
  images_upload_route: ImagesUploadRoute
  reorder_web_blocks_route: ReorderWebBlocksRoute
  website_layout: string
}

export interface Layout {
  web_blocks: WebBlock[]
}

export interface WebBlockParameters {
  data: any[]
}

export interface WebBlock {
  id: number
  type: string
  web_block: WebBlock2
}

export interface WebBlock2 {
  id: number
  data: any[]
  layout: Layout2
}

export interface Layout2 {
  id: number
  code: string
  data: Data
  name: string
  slug: string
  scope: string
  group_id: number
  blueprint: Blueprint
  created_at: string
  updated_at: string
  description: any
}

export interface Data {
  icon: string[]
  component: string
  fieldValue: FieldValue
  blockLayout: BlockLayout
  properties: {
    padding?: {
      top: number | null
      bottom: number | null
      left: number | null
      right: number | null
      unit: string
    }
    margin?: {
      top: number | null
      bottom: number | null
      left: number | null
      right: number | null
      unit: string
    }
  }
}

export interface FieldValue {
  id: string
  image: any
  button?: string
  headline?: string
  description?: string
  value?: string
}

export interface BlockLayout {
  marginTop: MarginTop
  marginLeft: MarginLeft
  paddingTop: PaddingTop
  marginRight: MarginRight
  paddingLeft: PaddingLeft
  marginBottom: MarginBottom
  paddingRight: PaddingRight
  paddingBottom: PaddingBottom
}

export interface MarginTop {
  unit: string
  value: string
}

export interface MarginLeft {
  unit: string
  value: string
}

export interface PaddingTop {
  unit: string
  value: string
}

export interface MarginRight {
  unit: string
  value: string
}

export interface PaddingLeft {
  unit: string
  value: string
}

export interface MarginBottom {
  unit: string
  value: string
}

export interface PaddingRight {
  unit: string
  value: string
}

export interface PaddingBottom {
  unit: string
  value: string
}

export interface Blueprint {
  value: string
}

export interface AddWebBlockRoute {
  name: string
  parameters: number
}

export interface UpdateModelHasWebBlocksRoute {
  name: string
}

export interface DeleteModelHasWebBlocksRoute {
  name: string
}

export interface ImagesUploadRoute {
  name: string
}

export interface ReorderWebBlocksRoute {
  name: string
  parameters: number
}
