import QuickLook
import SwiftUI

@MainActor
@Observable
final class GoodsOutDocumentOpener {
    private(set) var openingKey: String?
    var previewURL: URL?

    func isOpening(_ document: GoodsOutDocument) -> Bool {
        openingKey == document.key
    }

    func open(_ document: GoodsOutDocument, of reference: String, toasts: ToastCenter) {
        guard openingKey == nil else { return }
        openingKey = document.key
        Task {
            let result = await GoodsOutStore.shared.download(document, of: reference)
            openingKey = nil
            switch result {
            case .success(let url):
                previewURL = url
            case .failure(let problem):
                if !problem.message.isEmpty {
                    toasts.show(problem.message, tone: .danger)
                }
            }
        }
    }
}

struct GoodsOutDocumentPreview: ViewModifier {
    @Bindable var opener: GoodsOutDocumentOpener

    func body(content: Content) -> some View {
        content.quickLookPreview($opener.previewURL)
    }
}

extension View {
    func goodsOutDocumentPreview(_ opener: GoodsOutDocumentOpener) -> some View {
        modifier(GoodsOutDocumentPreview(opener: opener))
    }
}

struct GoodsOutDocumentRow: View {
    let document: GoodsOutDocument
    let reference: String
    let opener: GoodsOutDocumentOpener
    @Environment(ToastCenter.self) private var toasts

    var body: some View {
        ListRow(document.title, icon: .document) {
            opener.open(document, of: reference, toasts: toasts)
        } trailing: {
            GoodsOutLinkLabel(opener.isOpening(document) ? "Opening…" : "Open PDF")
        }
        .disabled(opener.openingKey != nil)
    }
}
