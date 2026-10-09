import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"


// To copy a text to clipboard
export const useCopyText = (textToCopy?: string | number) => {
    if(!textToCopy) return
    
    const textarea = document.createElement("textarea")
    textarea.value = textToCopy.toString()
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand("copy")
    textarea.remove()
    
    notify({
        // title: ctrans(''),
        title: ctrans('Text successfully copied to clipboard.'),
        type: "info"
    });
}