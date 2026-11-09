import SwiftUI

enum ScanFeedback: Hashable {
    case correct(sku: String, progress: String)
    case wrongItem(scanned: String, expected: String)
    case wrongLocation(scanned: String, expected: String, sku: String)
    case unknownBarcode(String)
    case notOnDelivery(sku: String, delivery: String)
    case alreadyComplete(required: Int)

    var title: String {
        switch self {
        case .correct(let sku, _): "\(sku) +1"
        case .wrongItem: "Wrong item — not counted"
        case .wrongLocation(let scanned, _, _): "This is \(scanned)"
        case .unknownBarcode: "Barcode not found"
        case .notOnDelivery(let sku, let delivery): "\(sku) is not on \(delivery)"
        case .alreadyComplete(let required): "All \(required) already picked"
        }
    }

    var message: String {
        switch self {
        case .correct(_, let progress): progress
        case .wrongItem(let scanned, let expected): "Scanned \(scanned) · expected \(expected)"
        case .wrongLocation(_, let expected, let sku): "Go to \(expected) to pick \(sku)"
        case .unknownBarcode(let code): "\(code) is not linked to a SKU or location"
        case .notOnDelivery: "Add it as an extra item?"
        case .alreadyComplete: "Extra scan not counted · scan the next item"
        }
    }

    var isCounted: Bool {
        if case .correct = self { return true }
        return false
    }

    var symbol: String {
        switch self {
        case .correct: "checkmark.circle.fill"
        case .wrongItem: "xmark.octagon.fill"
        case .wrongLocation, .alreadyComplete: "exclamationmark.triangle.fill"
        case .unknownBarcode: "questionmark.circle.fill"
        case .notOnDelivery: "plus.circle.fill"
        }
    }

    var background: Color {
        switch self {
        case .correct: AikuColor.success
        case .wrongItem: AikuColor.danger
        case .wrongLocation, .alreadyComplete: AikuColor.warning
        case .unknownBarcode, .notOnDelivery: AikuColor.surface
        }
    }

    var foreground: Color {
        switch self {
        case .unknownBarcode, .notOnDelivery: AikuColor.textPrimary
        default: .white
        }
    }
}

struct ScanFeedbackBanner: View {
    let feedback: ScanFeedback

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: feedback.symbol)
                .font(.system(size: 24))
                .foregroundStyle(feedback.background == AikuColor.surface ? AikuColor.danger : feedback.foreground)
            VStack(alignment: .leading, spacing: 2) {
                Text(feedback.title).font(.headline)
                Text(feedback.message).font(.subheadline).opacity(0.9)
            }
            Spacer(minLength: 0)
        }
        .foregroundStyle(feedback.foreground)
        .padding(14)
        .background(feedback.background, in: RoundedRectangle(cornerRadius: 12))
        .overlay {
            if feedback.background == AikuColor.surface {
                RoundedRectangle(cornerRadius: 12).stroke(AikuColor.dangerDot, lineWidth: 2)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityAddTraits(feedback.isCounted ? [] : .isStaticText)
    }
}
