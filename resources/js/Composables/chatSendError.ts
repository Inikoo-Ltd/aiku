/**
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright: 2026
 */
import { ctrans } from "@/Composables/useTrans"

/**
 * A 413 never reaches Laravel: the web server refuses the body before PHP is given it, so there
 * is no JSON message behind it and the usual `data.message` read comes back undefined. Saying
 * "something went wrong" for it leaves the agent watching a bubble that will never arrive, with
 * nothing to tell them the file was the problem.
 */
export const chatSendErrorText = (error: any, fallback: string): string => {
    if (error?.response?.status === 413) {
        return ctrans(
            "The attachment is too large for the server to accept. Send a smaller file, or fewer at once."
        )
    }

    const message = error?.response?.data?.message

    return typeof message === "string" && message !== "" ? message : fallback
}
