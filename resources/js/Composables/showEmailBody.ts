/*
 * A received email is shown the way it was designed, because the markup is sometimes the whole
 * message: photos sent from Gmail as Drive links exist only as links in the html, and a column
 * of stripped text leaves the agent with filenames they cannot open.
 */

interface EmailBodyMessage {
    html_body?: string | null
    is_retracted?: boolean
    edited_at?: string | null
}

/**
 * A translation sits underneath the message and replaces nothing, so it has no say here. What
 * does is the message no longer being what arrived: retracted, or edited into different text
 * the stored markup knows nothing about.
 */
export const showEmailBody = (message: EmailBodyMessage): boolean =>
    !!message.html_body && message.is_retracted !== true && !message.edited_at
