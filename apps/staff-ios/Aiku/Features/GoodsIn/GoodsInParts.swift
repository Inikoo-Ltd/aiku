import SwiftUI

struct GoodsInField: Identifiable, Hashable {
    let label: String
    let value: String

    var id: String { label }

    init(_ label: String, _ value: String) {
        self.label = label
        self.value = value
    }
}

struct GoodsInFieldGrid: View {
    let title: String
    let fields: [GoodsInField]

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title)
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 12, alignment: .topLeading), GridItem(.flexible(), spacing: 12, alignment: .topLeading)], alignment: .leading, spacing: 10) {
                ForEach(fields) { field in
                    VStack(alignment: .leading, spacing: 1) {
                        Text(field.label)
                            .font(.aiku(11))
                            .foregroundStyle(AikuColor.text3)
                        Text(field.value)
                            .font(.aiku(14, .semibold))
                            .foregroundStyle(AikuColor.text)
                            .lineLimit(2)
                            .minimumScaleFactor(0.8)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityElement(children: .combine)
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

struct GoodsInPhoto: View {
    let code: String
    var imageURL: String?
    var size: CGFloat = 48

    var body: some View {
        let label = "Product photo of \(code)"
        if let url = imageURL.flatMap(URL.init(string:)) {
            AsyncImage(url: url) { image in
                image.resizable().scaledToFill()
            } placeholder: {
                ProductThumb(size: size, label: label)
            }
            .frame(width: size, height: size)
            .clipShape(RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
            .accessibilityLabel(label)
        } else {
            ProductThumb(size: size, label: label)
        }
    }
}

struct GoodsInItemRow: View {
    let code: String
    let subtitle: String
    var chip: Chip?
    var done: String?
    var required: String?
    var quantityTone: Tone?
    var imageURL: String?
    var showsPhoto = true
    var identifier: String?
    var action: (() -> Void)?

    var body: some View {
        ListRow(code, subtitle: subtitle.isEmpty ? nil : subtitle, chips: chip.map { [$0] } ?? [], action: action) {
            if showsPhoto {
                GoodsInPhoto(code: code, imageURL: imageURL)
            }
        } trailing: {
            if let done {
                quantity(done)
            }
        }
        .accessibilityIdentifier(identifier ?? "goodsIn.item.\(code)")
    }

    private func quantity(_ done: String) -> some View {
        let doneText = Text(done)
            .font(.aiku(16, .bold))
            .foregroundColor(quantityTone?.foreground ?? AikuColor.text)
        let shown = required.map { doneText + Text(" / \($0)").font(.aiku(13, .medium)).foregroundColor(AikuColor.text3) } ?? doneText
        return shown
            .monospacedDigit()
            .lineLimit(1)
            .fixedSize()
    }
}

struct GoodsInCardList<Item: Identifiable, Row: View>: View {
    let items: [Item]
    @ViewBuilder let row: (Item) -> Row

    var body: some View {
        if !items.isEmpty {
            Card {
                ForEach(Array(items.enumerated()), id: \.element.id) { index, item in
                    if index > 0 {
                        RowDivider()
                    }
                    row(item)
                }
            }
        }
    }
}

struct GoodsInViewTabs<Option: Hashable>: View {
    let options: [(Option, String)]
    let active: Option?
    let onSelect: (Option) -> Void

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 0) {
                ForEach(options, id: \.0) { option, label in
                    let isActive = option == active
                    Button {
                        if !isActive { onSelect(option) }
                    } label: {
                        Text(label)
                            .font(.aiku(12, .semibold))
                            .foregroundStyle(isActive ? AikuColor.link : AikuColor.text3)
                            .lineLimit(1)
                            .padding(.horizontal, 12)
                            .frame(maxHeight: .infinity)
                            .background(isActive ? AikuColor.surface : Color.clear, in: RoundedRectangle(cornerRadius: 7))
                            .padding(.horizontal, 2)
                            .padding(.vertical, 4)
                            .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .frame(height: 44)
                    .accessibilityAddTraits(isActive ? [.isSelected, .isButton] : .isButton)
                    .accessibilityIdentifier("goodsIn.tab.\(label)")
                }
            }
            .padding(.horizontal, 2)
        }
        .scrollBounceBehavior(.basedOnSize, axes: .horizontal)
        .background(AikuColor.border, in: RoundedRectangle(cornerRadius: 10))
        .clipShape(RoundedRectangle(cornerRadius: 10))
    }
}

struct GoodsInScanPrompt: View {
    let title: String
    let message: String
    var identifier = "goodsIn.scanPrompt"
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 8) {
                    AppIcon.scan.image
                        .font(.system(size: 18, weight: .semibold))
                        .accessibilityHidden(true)
                    Text(title)
                        .font(.aiku(14, .bold))
                }
                Text(message)
                    .font(.aiku(13))
                    .multilineTextAlignment(.leading)
            }
            .foregroundStyle(AikuColor.onScan)
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
            .contentShape(RoundedRectangle(cornerRadius: 12))
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier(identifier)
    }
}

struct GoodsInDisabledNotice: View {
    let action: MobileGoodsInAction
    var fallbackTitle = ""

    var body: some View {
        HStack(spacing: 10) {
            AppIcon.lock.image
                .font(.system(size: 18))
                .foregroundStyle(AikuColor.warn)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text(action.label.isEmpty ? fallbackTitle : action.label)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(action.disabledReason ?? "")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.warnInk)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .frame(minHeight: 44)
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("goodsIn.disabled.\(action.key)")
    }
}

enum GoodsInActionTone {
    case neutral
    case primary
    case caution
    case danger

    var tile: Tone {
        switch self {
        case .neutral: .neutral
        case .primary: .info
        case .caution: .warn
        case .danger: .danger
        }
    }
}

struct GoodsInActionLinkRow: View {
    let icon: AppIcon
    let title: String
    let subtitle: String
    var tone: GoodsInActionTone = .neutral
    var identifier: String?
    var action: (() -> Void)?

    var body: some View {
        ListRow(title, subtitle: subtitle, icon: icon, iconTone: tone.tile, action: action)
            .opacity(action == nil && tone != .primary ? 0.7 : 1)
            .accessibilityIdentifier(identifier ?? "goodsIn.link.\(title)")
    }
}

struct GoodsInChoiceRow: View {
    let title: String
    var subtitle: String?
    let isSelected: Bool
    var identifier: String?
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    if let subtitle, !subtitle.isEmpty {
                        Text(subtitle)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                            .multilineTextAlignment(.leading)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                Image(systemName: isSelected ? "largecircle.fill.circle" : "circle")
                    .font(.system(size: 22))
                    .foregroundStyle(isSelected ? AikuColor.primary : AikuColor.borderStrong)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .frame(minHeight: 56)
            .background(isSelected ? AikuColor.soft : AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(isSelected ? AikuColor.softBd : AikuColor.border))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? [.isSelected, .isButton] : .isButton)
        .accessibilityIdentifier(identifier ?? "goodsIn.choice.\(title)")
    }
}

struct GoodsInQuantityStepper: View {
    let value: Double
    var minimum: Double = 0
    var maximum: Double = .greatestFiniteMagnitude
    let onChange: (Double) -> Void

    var body: some View {
        HStack(spacing: 12) {
            button(.minus, label: "Less", isEnabled: value > minimum) {
                onChange(Swift.min(Swift.max(value - 1, minimum), maximum))
            }
            Text(GoodsInText.number(value))
                .font(.aiku(26, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .minimumScaleFactor(0.6)
                .frame(maxWidth: .infinity)
                .accessibilityLabel("Quantity \(GoodsInText.number(value))")
                .accessibilityIdentifier("goodsIn.stepper.value")
            button(.plus, label: "More", isEnabled: value < maximum) {
                onChange(Swift.min(Swift.max(value + 1, minimum), maximum))
            }
        }
        .padding(8)
        .cardSurface()
    }

    private func button(_ icon: AppIcon, label: String, isEnabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            icon.image
                .font(.system(size: 20, weight: .semibold))
                .foregroundStyle(isEnabled ? AikuColor.text : AikuColor.faint)
                .frame(width: 52, height: 52)
                .background(AikuColor.surface2, in: RoundedRectangle(cornerRadius: 10))
                .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.borderStrong))
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(!isEnabled)
        .accessibilityLabel(label)
        .accessibilityIdentifier("goodsIn.stepper.\(label.lowercased())")
    }
}

struct GoodsInSheetProblem: View {
    let problem: String?

    var body: some View {
        if let problem {
            InlineAlert(tone: .danger, title: problem)
                .accessibilityIdentifier("goodsIn.sheetProblem")
        }
    }
}

struct GoodsInHelper: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        Text(text)
            .font(.aiku(12))
            .foregroundStyle(AikuColor.text4)
            .fixedSize(horizontal: false, vertical: true)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 4)
    }
}

struct GoodsInEvents: ViewModifier {
    let event: GoodsInEvent?
    let onHandled: () -> Void
    var onSheetProblem: ((String) -> Void)?
    var doneText: () -> String? = { nil }
    var onDone: () -> Void = {}
    @Environment(ToastCenter.self) private var toasts

    func body(content: Content) -> some View {
        content.onChange(of: event) { _, event in
            guard let event else { return }
            switch event {
            case let .done(message, isNothingChanged, wasRaised):
                let tone: Tone = wasRaised ? .warn : isNothingChanged ? .info : .success
                if let text = message ?? doneText() {
                    toasts.show(text, tone: tone, sticky: false)
                }
                onDone()
            case .refused(let message):
                let text = message.isEmpty ? GoodsInText.server : message
                if let onSheetProblem {
                    onSheetProblem(text)
                } else {
                    toasts.show(text, tone: .danger)
                }
            }
            onHandled()
        }
    }
}

extension View {
    func goodsInEvents(
        _ event: GoodsInEvent?,
        onHandled: @escaping () -> Void,
        onSheetProblem: ((String) -> Void)? = nil,
        doneText: @escaping () -> String? = { nil },
        onDone: @escaping () -> Void = {}
    ) -> some View {
        modifier(GoodsInEvents(event: event, onHandled: onHandled, onSheetProblem: onSheetProblem, doneText: doneText, onDone: onDone))
    }
}

struct GoodsInStageStepper: View {
    let stage: GoodsInStage
    let isComplete: Bool

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 4) {
                ForEach(GoodsInStage.allCases, id: \.self) { step in
                    let status = status(of: step)
                    Text(step.title)
                        .font(.aiku(11, .bold))
                        .lineLimit(1)
                        .fixedSize()
                        .foregroundStyle(status == .done ? Color.white : status == .current ? AikuColor.onPrimary : AikuColor.text3)
                        .padding(.horizontal, 10)
                        .frame(height: 28)
                        .background(status == .done ? AikuColor.success : status == .current ? AikuColor.primary : AikuColor.surface, in: Capsule())
                        .overlay(Capsule().strokeBorder(status == .upcoming ? AikuColor.border : Color.clear))
                        .accessibilityLabel(status == .done ? "\(step.title), done" : status == .current ? "\(step.title), current step" : step.title)
                }
            }
            .padding(.horizontal, 16)
        }
        .scrollBounceBehavior(.basedOnSize, axes: [.horizontal, .vertical])
        .padding(.horizontal, -16)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Stock delivery progress")
    }

    private enum Status { case done, current, upcoming }

    private func status(of step: GoodsInStage) -> Status {
        if step.rawValue < stage.rawValue || (isComplete && step == stage) { return .done }
        return step == stage ? .current : .upcoming
    }
}

struct GoodsInStepItem: Identifiable {
    let id: String
    let title: String
    let isDone: Bool
    let isCurrent: Bool
}

struct GoodsInSteps: View {
    let steps: [GoodsInStepItem]
    let label: String

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 4) {
                ForEach(steps) { step in
                    Text(step.title)
                        .font(.aiku(11, .bold))
                        .lineLimit(1)
                        .fixedSize()
                        .foregroundStyle(step.isDone ? Color.white : step.isCurrent ? AikuColor.onPrimary : AikuColor.text3)
                        .padding(.horizontal, 10)
                        .frame(height: 28)
                        .background(step.isDone ? AikuColor.success : step.isCurrent ? AikuColor.primary : AikuColor.surface, in: Capsule())
                        .overlay(Capsule().strokeBorder(step.isDone || step.isCurrent ? Color.clear : AikuColor.border))
                }
            }
            .padding(.horizontal, 16)
        }
        .scrollBounceBehavior(.basedOnSize, axes: [.horizontal, .vertical])
        .padding(.horizontal, -16)
        .accessibilityElement(children: .contain)
        .accessibilityLabel(label)
    }
}

struct GoodsInLoadingScreen: View {
    let title: String
    let kicker: String
    let failedTitle: String
    let problem: String?
    let retry: () -> Void

    var body: some View {
        AppScreen(title: title, kicker: kicker) {
            if let problem {
                GoodsOutLoadProblem(title: failedTitle, message: problem, retry: retry)
            } else {
                GoodsOutSkeletonCard(rows: 2)
                GoodsOutSkeletonCard(rows: 4)
            }
        }
    }
}

struct GoodsInAccessGate<Content: View>: View {
    let isAllowed: (MobileWarehouse.Abilities) -> Bool
    var permission = "incoming.view"
    @ViewBuilder let content: Content
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouse = warehouseStore.selected {
            if isAllowed(warehouse.can) {
                content
            } else {
                SystemNoAccessView(module: "Goods in", warehouse: warehouse.name, permission: permission)
            }
        } else {
            AppScreen(title: "Goods in") {
                GoodsOutSkeletonCard(rows: 3)
            }
        }
    }
}
