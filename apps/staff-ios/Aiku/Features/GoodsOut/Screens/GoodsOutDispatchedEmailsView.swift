import SwiftUI

struct GoodsOutDispatchedEmailsView: View {
    let reference: String
    @State private var previewing: GoodsOutEmail?
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Dispatched emails", kicker: "Delivery note \(note.reference)") {
                if note.emails.isEmpty {
                    EmptyState(icon: .email, title: "No emails yet", message: "Emails appear here once the delivery note is dispatched.")
                } else {
                    Card {
                        ForEach(Array(note.emails.enumerated()), id: \.element.id) { index, email in
                            if index > 0 {
                                RowDivider()
                            }
                            ListRow(email.title, subtitle: email.subtitle, icon: .email, iconTone: email.status.tone, chips: [Chip(email.status.title, email.status.tone)]) {
                                previewing = email
                            } trailing: {
                                GoodsOutLinkLabel("Preview")
                            }
                            .accessibilityHint("Shows a preview of the email")
                        }
                    }
                }
                HelperText("States come from the email provider: sent, delivered, opened, clicked, bounced, spam. Read-only.")
            }
            .aikuSheet(item: $previewing) { email in
                SheetScaffold(title: email.title, subtitle: email.subtitle) {
                    Card {
                        KeyValueRow("To", email.recipient)
                        RowDivider()
                        KeyValueRow("Status", email.status.title, valueTone: email.status.tone)
                        RowDivider()
                        KeyValueRow("Delivery note", note.reference)
                    }
                    Text("[Email body]")
                        .font(.aiku(14))
                        .italic()
                        .foregroundStyle(AikuColor.text3)
                        .padding(14)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .cardSurface()
                } footer: {
                    AikuButton("Close", kind: .tertiary) { previewing = nil }
                }
            }
        }
    }
}
