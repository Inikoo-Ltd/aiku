import QuickLook
import SwiftUI

struct ClockingAttachmentList: View {
    let attachments: [ClockingAttachment]
    @Environment(ToastCenter.self) private var toasts
    @State private var openingID: Int?
    @State private var previewURL: URL?

    var body: some View {
        if !attachments.isEmpty {
            VStack(alignment: .leading, spacing: 4) {
                ForEach(attachments) { attachment in
                    Button {
                        open(attachment)
                    } label: {
                        HStack(spacing: 8) {
                            if openingID == attachment.id {
                                ProgressView().controlSize(.small)
                            } else {
                                AppIcon.document.image.font(.system(size: 13))
                            }
                            Text(attachment.name)
                                .font(.aiku(13, .semibold))
                                .lineLimit(1)
                                .truncationMode(.middle)
                        }
                        .foregroundStyle(AikuColor.link)
                        .frame(minHeight: 32)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .disabled(openingID != nil)
                    .accessibilityLabel("Open \(attachment.name)")
                }
            }
            .quickLookPreview($previewURL)
        }
    }

    private func open(_ attachment: ClockingAttachment) {
        openingID = attachment.id
        Task {
            do {
                previewURL = try await ClockingStore.shared.download(attachment)
            } catch APIError.offline {
                toasts.show("No connection. Try again with internet.", tone: .danger)
            } catch {
                toasts.show(L10n.format("Couldn’t open %@", attachment.name), tone: .danger)
            }
            openingID = nil
        }
    }
}
