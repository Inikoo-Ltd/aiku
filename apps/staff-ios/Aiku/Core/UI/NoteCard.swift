import SwiftUI

enum NoteKind: CaseIterable, Sendable {
    case shippingLabel
    case customer
    case warehouse
    case fulfilmentCustomer
    case fulfilmentPublic
    case fulfilmentPrivate
    case plain

    static let headerInk = RGBA(0x1F2937)
    static let bodyTintOpacity = Double(0x11) / 255
    private static let fulfilmentAccentWeight = 0.4

    var accent: RGBA {
        switch self {
        case .shippingLabel: RGBA(0x93C5FD)
        case .customer: RGBA(0x599FF0)
        case .warehouse: RGBA(0xFCD34D)
        case .fulfilmentCustomer: RGBA(0x009EFF)
        case .fulfilmentPublic: RGBA(0xE6007E)
        case .fulfilmentPrivate: RGBA(0x5000E6)
        case .plain: RGBA(0xAAAAAA)
        }
    }

    var header: RGBA {
        isFulfilment ? accent.mixed(Self.fulfilmentAccentWeight, with: .white) : accent
    }

    var bodyTint: RGBA {
        accent.opacity(Self.bodyTintOpacity)
    }

    private var isFulfilment: Bool {
        switch self {
        case .fulfilmentCustomer, .fulfilmentPublic, .fulfilmentPrivate: true
        case .shippingLabel, .customer, .warehouse, .plain: false
        }
    }
}

struct NoteCard: View {
    let title: String
    let icon: AppIcon
    var kind: NoteKind?
    var text: String?

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(spacing: 8) {
                Text(L10n.text(title))
                    .font(.aiku(13, .bold))
                    .frame(maxWidth: .infinity, alignment: .leading)
                icon.image
                    .font(.system(size: 14))
                    .accessibilityHidden(true)
            }
            .foregroundStyle(headerInk)
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
            .background(headerFill)
            Group {
                if let text, !text.isEmpty {
                    Text(text)
                        .foregroundStyle(AikuColor.text2)
                } else {
                    Text("No note added")
                        .italic()
                        .foregroundStyle(AikuColor.faint)
                }
            }
            .font(.aiku(14))
            .fixedSize(horizontal: false, vertical: true)
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .background(bodyFill)
        .background(AikuColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(borderColor))
    }

    private var headerFill: Color {
        kind?.header.color ?? AikuColor.soft
    }

    private var headerInk: Color {
        kind == nil ? AikuColor.ink : NoteKind.headerInk.color
    }

    private var bodyFill: Color {
        kind?.bodyTint.color ?? Color.clear
    }

    private var borderColor: Color {
        kind?.header.color ?? AikuColor.border
    }
}
