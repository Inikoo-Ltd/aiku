// --------------------------
// Last message from session
// --------------------------
export interface LastMessage {
	message?: string
	sender_type: "guest" | "user" | "agent" | "system" | "system_campaign"
	created_at?: string
	created_at_timestamp?: number
	is_read: boolean
}

// --------------------------
// Shop / Organisation (inbox scope)
// --------------------------
export interface ChatInboxShop {
	id: number
	name: string
	slug: string
	domain?: string
}

export interface ChatInboxOrganisation {
	id: number
	name: string
	slug: string
}

// --------------------------
// Session data from API
// --------------------------
export type ChatChannel = "website" | "whatsapp"

export interface SessionAPI {
	id: string
	ulid: string
	channel?: ChatChannel
	status: "waiting" | "active" | "closed"
	guest_identifier: string | null
	contact_name: string | null
	created_at: string
	priority: string
	is_spam?: boolean
	is_trashed?: boolean
	is_highlighted?: boolean
	customer: boolean
	image?: string
	shop?: ChatInboxShop | null
	organisation?: ChatInboxOrganisation | null
	last_message?: LastMessage
	assigned_agent?: {
		id: string
		name: string
	}
	unread_count: number
	can_send_non_template_message?: boolean
	message_count: number
	duration: string
	ai_summary: {
		summary: string
		key_points: string
		sentiment: string
	} | null
	customer?: {
		id: string
		name: string
		slug?: string
		email?: string
		phone?: string
	} | null
	web_user?: {
		id: string
		name: string
		slug: string
		email: string
		phone: string
		organisation: string
		organisation_slug: string
		shop: string
		shop_slug: string
	} | null
	guest_profile?: {
		name: string
		email: string
		phone: string
	} | null
}

// --------------------------
// Pagination info from API
// --------------------------
export interface Pagination {
	current_page: number
	per_page: number
	total: number
	last_page: number
	has_more: boolean
}

// --------------------------
// Full API response
// --------------------------
export interface ChatSessionsResponse {
	success: boolean
	message: string
	data: {
		sessions: SessionAPI[]
		pagination: Pagination
	}
}

// --------------------------
// Contact interface for frontend
// --------------------------
export interface Contact {
	id: string
	name: string
	ulid: string
	channel?: ChatChannel
	avatar: string
	lastMessage: string
	priority: string
	lastMessageTime?: string
	lastMessageAge?: string
	unread: number
	status: "waiting" | "active" | "closed" | string
	is_spam?: boolean
	is_rubbish?: boolean
	can_dispose?: boolean
	open_tickets_count?: number
	blocking_tickets_count?: number
	noise?: { label: string; note: string | null; source: string | null; automatic: boolean } | null
	is_highlighted?: boolean
	messages?: ChatMessage[]
	webUser?: {
		id: string
		name: string
		customer_id?: number | null
		slug: string
		email: string
		phone: string
		organisation: string
		organisation_slug: string
		shop: string
		shop_slug: string
	} | null
	guest_profile?: {
		name: string
		email: string
		phone: string
	} | null
	metadata?: {
		name?: string
		email?: string
		phone?: string
		[key: string]: any
	} | null
	phone_number?: string | null
	agent?: {
		id: string
		name: string
	}
	shop?: ChatInboxShop | null
	organisation?: ChatInboxOrganisation | null
	ai_summary?: {
		summary: string
		key_points: string[]
		sentiment: string
	} | null
}

// --------------------------
// Inbox grouping (one shop = one inbox)
// --------------------------
export interface ChatInboxGroup {
	key: number | string
	shopName: string
	organisationName: string
	unread: number
	contacts: Contact[]
}

export interface ChatMessageReactionGroup {
	emoji: string
	count: number
	reactors: { type: string; id: number | null }[]
}

export interface ChatMessageAttachment {
	id: number
	is_image: boolean
	media_url: { original: string; webp?: string } | null
	original_url: string
	file_name: string
	file_size: number
	file_mime: string
	download_route: { name: string; parameters: Record<string, any>; method: string; url: string }
}

export interface ChatMessage {
	id: string
	message?: string
	ulid?: string
	message_text: string
	html_body?: string | null
	message_type?: "text" | "image" | "file"
	sender_type: "guest" | "user" | "agent" | "system" | "system_campaign"
	created_at: string
	is_read?: boolean
	reactions?: ChatMessageReactionGroup[]
	media_url?: { original: string; webp?: string } | null
	file_name?: string | null
	file_size?: number | null
	file_mime?: string | null
	download_route?: { url: string } | null
	attachments?: ChatMessageAttachment[]
}
