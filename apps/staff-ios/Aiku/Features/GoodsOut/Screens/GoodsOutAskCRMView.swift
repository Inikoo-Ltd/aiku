import SwiftUI

struct GoodsOutAskCRMView: View {
    let reference: String
    @Environment(ToastCenter.self) private var toasts
    @State private var message = ""
    @State private var isAddingTask = false
    @FocusState private var isMessageFocused: Bool
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Ask CRM", kicker: "Delivery note \(note.reference) · \(note.state.title)") {
                if note.messages.isEmpty, note.tasks.isEmpty {
                    EmptyState(icon: .chat, title: "No messages yet", message: "Ask customer services about this delivery note.")
                }
                ForEach(note.messages) { item in
                    GoodsOutChatBubble(message: item)
                }
                if !note.tasks.isEmpty {
                    Card {
                        ForEach(Array(note.tasks.enumerated()), id: \.element.id) { index, task in
                            if index > 0 {
                                RowDivider()
                            }
                            ListRow(task.title, subtitle: task.subtitle, icon: .check, iconTone: task.isOpen ? .info : .success, chips: [task.chip]) {
                                store.toggleTask(note.reference, task: task.id)
                            }
                            .accessibilityHint(task.isOpen ? "Marks the task as done" : "Opens the task again")
                        }
                    }
                }
            } footer: {
                HStack(spacing: 8) {
                    FieldBox(isFocused: isMessageFocused, height: 48) {
                        TextField("Message customer services", text: $message)
                            .font(.aiku(15))
                            .foregroundStyle(AikuColor.text)
                            .focused($isMessageFocused)
                            .submitLabel(.send)
                            .onSubmit { send(note) }
                            .padding(.horizontal, 14)
                            .accessibilityLabel("Message")
                    }
                    AikuButton("Send", size: .regular, fullWidth: false) {
                        send(note)
                    }
                    .accessibilityIdentifier("goodsOut.sendMessage")
                }
                AikuButton("Add task for this delivery note", icon: .check, kind: .tertiary) {
                    isAddingTask = true
                }
                .accessibilityIdentifier("goodsOut.addTask")
            }
            .aikuSheet(isPresented: $isAddingTask) {
                GoodsOutAddTaskSheet(note: note) { isAddingTask = false }
            }
        }
    }

    private func send(_ note: GoodsOutDeliveryNote) {
        guard store.sendMessage(note.reference, text: message) != nil else {
            toasts.show("Type a message first", tone: .warn)
            return
        }
        message = ""
    }
}

struct GoodsOutChatBubble: View {
    let message: GoodsOutChatMessage

    var body: some View {
        HStack(spacing: 0) {
            if message.isMine {
                Spacer(minLength: 64)
            }
            bubble
            if !message.isMine {
                Spacer(minLength: 64)
            }
        }
        .accessibilityElement(children: .combine)
    }

    private var bubble: some View {
        VStack(alignment: message.isMine ? .trailing : .leading, spacing: 3) {
            Text(message.meta)
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
            Text(message.text)
                .font(.aiku(14))
                .foregroundStyle(message.isMine ? AikuColor.onPrimary : AikuColor.text)
                .fixedSize(horizontal: false, vertical: true)
                .padding(.horizontal, 12)
                .padding(.vertical, 10)
                .background(message.isMine ? AikuColor.primary : AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
                .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(message.isMine ? .clear : AikuColor.border))
        }
    }
}

private struct GoodsOutAddTaskSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    @State private var title = ""
    @State private var showsError = false
    private let store = GoodsOutStore.shared

    var body: some View {
        SheetScaffold(title: "Add task", subtitle: "A staff task linked to delivery note \(note.reference)") {
            AikuTextField("Task", text: $title, placeholder: "Recount B-01-04", error: showsError ? "Describe the task" : nil)
            HelperText("The task is assigned to you. Customer services and supervisors see it on the delivery note.")
        } footer: {
            AikuButton("Add task") {
                let previous = store.addTask(note.reference, title: title)
                guard previous != nil else {
                    showsError = true
                    return
                }
                close()
                GoodsOutFeedback.done("Task added to \(note.reference)", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.confirmAddTask")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
