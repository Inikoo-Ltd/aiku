// --------------------------
// Last message from session
// --------------------------
export interface LastMessage {
	message?: string
	sender_type: "guest" | "agent" | "system"
	created_at?: string // string dari backend
	created_at_timestamp?: number
	is_read: boolean
}

// --------------------------
// Session data from API
// --------------------------
export interface SessionAPI {
	ulid: string
	status: "waiting" | "active" | "closed" | string
	guest_identifier: string
	created_at: string
	created_at_timestamp: number
	customer: boolean
	last_message?: LastMessage
	assigned_agent?: any | null
	unread_count: number
	message_count: number
	duration: string
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
	avatar: string
	lastMessage: string
	lastMessageTime?: Date
	lastMessageTimestamp?: number
	unread: number
	status: "waiting" | "active" | "closed" | string
}

export interface ChatMessage {}
