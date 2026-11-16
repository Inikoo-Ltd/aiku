import SwiftUI

struct StaffChatView: View {
    let title: String
    @State private var store: StaffChatStore
    @Environment(\.scenePhase) private var scenePhase
    @FocusState private var isMessageFocused: Bool

    init(kind: StaffChatContextKind, contextID: Int, title: String, service: StaffChatService = .live(.shared)) {
        self.title = title
        _store = State(initialValue: StaffChatStore(kind: kind, contextID: contextID, service: service))
    }

    var body: some View {
        AppScreen(title: "Ask CRM", kicker: kicker, scrolls: false) {
            StaffChatTranscript(store: store)
        } footer: {
            if store.conversation != nil {
                composer
            }
        }
        .task(id: scenePhase == .active) {
            guard scenePhase == .active else { return }
            await store.run()
        }
    }

    private var kicker: String {
        let label = store.conversation?.contextLabel?.trimmingCharacters(in: .whitespacesAndNewlines)
        return "\(store.kind.contextTitle) \(label?.isEmpty == false ? label! : title)"
    }

    @ViewBuilder
    private var composer: some View {
        if let sendError = store.sendError {
            InlineAlert(title: "Message not sent", message: sendError)
        }
        HStack(alignment: .bottom, spacing: 8) {
            FieldBox(isFocused: isMessageFocused, height: 48, growsVertically: true) {
                TextField("Message customer services", text: $store.draft, axis: .vertical)
                    .font(.aiku(15))
                    .foregroundStyle(AikuColor.text)
                    .lineLimit(1...5)
                    .focused($isMessageFocused)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 12)
                    .accessibilityLabel("Message")
                    .accessibilityIdentifier("staffChat.message")
            }
            AikuButton("Send", size: .regular, isLoading: store.isSending, fullWidth: false) {
                Task { await store.send() }
            }
            .disabled(!store.canSend)
            .accessibilityIdentifier("staffChat.send")
        }
    }
}

private struct StaffChatTranscript: View {
    let store: StaffChatStore
    @Environment(\.signOut) private var signOut

    var body: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 14) {
                    content
                }
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            .defaultScrollAnchor(.bottom)
            .scrollDismissesKeyboard(.interactively)
            .refreshable { await store.refresh() }
            .onChange(of: store.messages.last?.id) { _, lastID in
                guard let lastID else { return }
                withAnimation(.easeOut(duration: 0.2)) {
                    proxy.scrollTo(lastID, anchor: .bottom)
                }
            }
            .onChange(of: store.messages.first?.id) { previousFirstID, _ in
                guard let previousFirstID else { return }
                proxy.scrollTo(previousFirstID, anchor: .top)
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        if store.isSessionExpired {
            InlineAlert(title: "Your session has ended", message: "Sign in again to keep chatting.") {
                LinkButton("Sign in again") { signOut() }
            }
        } else if let loadError = store.loadError {
            InlineAlert(title: "Couldn’t open the conversation", message: loadError) {
                LinkButton("Try again") {
                    Task { await store.refresh() }
                }
            }
        }
        if store.conversation == nil {
            if store.loadError == nil, !store.isSessionExpired {
                Card {
                    SkeletonRow()
                    SkeletonRow()
                    SkeletonRow()
                }
            }
        } else {
            if !store.participantNames.isEmpty {
                HelperText("With \(store.participantNames.joined(separator: ", "))")
            }
            if store.hasOlder {
                olderMessagesRow
            }
            if store.messages.isEmpty {
                EmptyState(icon: .chat, title: "No messages yet", message: "Write to customer services below. They answer in Aiku, and replies show here.")
            } else {
                ForEach(store.messages) { message in
                    StaffChatBubble(message: message)
                        .id(message.id)
                }
            }
        }
    }

    private var olderMessagesRow: some View {
        HStack(spacing: 8) {
            if store.isLoadingOlder {
                ProgressView()
                Text("Loading older messages…")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            } else {
                LinkButton("Show older messages") {
                    Task { await store.loadOlder() }
                }
            }
        }
        .frame(maxWidth: .infinity, minHeight: 44)
        .task(id: store.messages.first?.id) {
            await store.loadOlder()
        }
    }
}

struct StaffChatBubble: View {
    let message: StaffChatMessage

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
            Text(StaffChatFormat.meta(message))
                .font(.aiku(11))
                .foregroundStyle(AikuColor.text3)
            if let imageURL = message.imageUrl.flatMap(URL.init(string:)) {
                AsyncImage(url: imageURL) { image in
                    image.resizable().scaledToFit()
                } placeholder: {
                    SkeletonBlock(width: 160, height: 120, radius: 12)
                }
                .frame(maxHeight: 220)
                .clipShape(RoundedRectangle(cornerRadius: 12))
                .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(AikuColor.border))
                .accessibilityLabel("Image sent in the conversation")
            }
            if let text = message.body?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty {
                Text(text)
                    .font(.aiku(14))
                    .foregroundStyle(message.isMine ? AikuColor.onPrimary : AikuColor.text)
                    .fixedSize(horizontal: false, vertical: true)
                    .textSelection(.enabled)
                    .padding(.horizontal, 12)
                    .padding(.vertical, 10)
                    .background(message.isMine ? AikuColor.primary : AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
                    .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(message.isMine ? .clear : AikuColor.border))
            }
        }
    }
}
