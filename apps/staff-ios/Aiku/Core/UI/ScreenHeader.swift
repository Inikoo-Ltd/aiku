import SwiftUI

enum HeaderTrailing {
    case none
    case warehouseMenu
    case more
}

struct ScreenHeader<Accessory: View>: View {
    @Environment(AppRouter.self) private var router

    let title: String
    var subtitle: String?
    var eyebrow: String?
    var showsBack = true
    var trailing: HeaderTrailing = .warehouseMenu
    @ViewBuilder var accessory: Accessory

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 4) {
                if showsBack {
                    HeaderIconButton(systemImage: AppIcon.back, label: "Back", isFilled: false) {
                        router.back()
                    }
                }
                VStack(alignment: .leading, spacing: 0) {
                    if let eyebrow {
                        Text(eyebrow)
                            .font(.caption)
                            .foregroundStyle(AikuColor.indigoMuted)
                    }
                    Text(title)
                        .font(.title3.bold())
                        .foregroundStyle(.white)
                    if let subtitle {
                        Text(subtitle)
                            .font(.caption)
                            .foregroundStyle(AikuColor.indigoMuted)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(.leading, showsBack ? 0 : 4)

                switch trailing {
                case .none:
                    EmptyView()
                case .warehouseMenu:
                    HeaderIconButton(systemImage: AppIcon.menu, label: "Warehouse menu") {
                        router.open(.warehouseMenu)
                    }
                case .more:
                    HeaderIconButton(systemImage: AppIcon.more, label: "More actions") {}
                }
            }
            accessory
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 14)
        .background(AikuColor.indigo.ignoresSafeArea(edges: .top))
    }
}

extension ScreenHeader where Accessory == EmptyView {
    init(title: String, subtitle: String? = nil, eyebrow: String? = nil, showsBack: Bool = true, trailing: HeaderTrailing = .warehouseMenu) {
        self.init(title: title, subtitle: subtitle, eyebrow: eyebrow, showsBack: showsBack, trailing: trailing) { EmptyView() }
    }
}

struct HeaderIconButton: View {
    let systemImage: String
    let label: String
    var isFilled = true
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Image(systemName: systemImage)
                .font(.system(size: 18, weight: .medium))
                .foregroundStyle(.white)
                .frame(width: 44, height: 44)
                .background(isFilled ? Color.white.opacity(0.14) : .clear, in: RoundedRectangle(cornerRadius: 8))
        }
        .accessibilityLabel(label)
    }
}
