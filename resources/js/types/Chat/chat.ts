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
// Session data from API
// --------------------------
export interface SessionAPI {
	id: string
	ulid: string
	status: "waiting" | "active" | "closed"
	guest_identifier: string | null
	contact_name: string | null
	created_at: string
	created_at_timestamp: number
	priority: string
	customer: boolean
	last_message?: LastMessage
	assigned_agent?: any | null
	unread_count: number
	message_count: number
	duration: string
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
	lastMessageTimestamp?: number
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
}

export interface ChatMessage {
	id: string
	message: string
	ulid: string
	message_text: string
	sender_type: "guest" | "user" | "agent" | "system"
	created_at: string
	created_at_timestamp: number
	is_read: boolean
}
