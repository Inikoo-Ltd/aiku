// --------------------------
// Last message from session
// --------------------------
export interface LastMessage {
	message?: string
	sender_type: "guest" | "user" | "agent" | "system"
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
export interface SessionAPI {
	id: string
	ulid: string
	status: "waiting" | "active" | "closed"
	guest_identifier: string | null
	contact_name: string | null
	created_at: string
	priority: string
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
	message_count: number
	duration: string
	ai_summary: {
		summary: string
		key_points: string
		sentiment: string
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
	avatar: string
	lastMessage: string
	priority: string
	lastMessageTime?: string
	unread: number
	status: "waiting" | "active" | "closed" | string
	messages?: ChatMessage[]
	webUser?: {
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

export interface ChatMessage {
	id: string
	message?: string
	ulid?: string
	message_text: string
	sender_type: "guest" | "user" | "agent" | "system"
	created_at: string
	is_read?: boolean
}
