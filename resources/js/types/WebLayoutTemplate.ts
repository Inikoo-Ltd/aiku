import { Links, Meta } from '@/types/Table'

export interface WebLayoutTemplate {
	id: number
	name: string
	type?: string
	scope?: string
	blocks?: Array<Record<string, any>>
	blocks_count?: number
	created_at?: string
	username?: string
}

export interface WebLayoutTemplateList {
	data: WebLayoutTemplate[]
	meta?: Meta
	links?: Links
}
