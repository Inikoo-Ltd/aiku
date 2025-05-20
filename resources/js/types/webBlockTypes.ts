export interface Root {
  data: Daum[]
}

export interface Daum {
  id: number
  name: string
  scope: string
  show:Boolean
  webBlockTypes: WebBlockType[]
  category : string
}

export interface WebBlockType {
  id: number
  code: string
  scope: string
  name: string
  description: any
  blueprint: Blueprint
  data: any
  created_at: string
  updated_at: string
}

export interface Blueprint {
  value?: string
  defaultParameters?: DefaultParameters
}

export interface DefaultParameters {
  navigation?: Navigation
  socials?: Social[]
  copyRight?: CopyRight
  navigations?: Navigation2[]
}

export interface Navigation {
  categories: Category[]
}

export interface Category {
  name: string
  featured: Featured[]
}

export interface Featured {
  href: string
  name: string
}

export interface Social {
  id: number
  href: string
  icon: string
  label: string
}

export interface CopyRight {
  href: string
  label: string
}

export interface Navigation2 {
  id: number
  data: any
  type: string
  title: string
}
