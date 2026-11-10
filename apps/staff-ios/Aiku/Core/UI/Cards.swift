import SwiftUI

struct Card<Content: View>: View {
    var padding: CGFloat = 0
    var spacing: CGFloat = 0
    @ViewBuilder var content: Content

    init(padding: CGFloat = 0, spacing: CGFloat = 0, @ViewBuilder content: () -> Content) {
        self.padding = padding
        self.spacing = spacing
        self.content = content()
    }

    var body: some View {
        VStack(alignment: .leading, spacing: spacing) {
            content
        }
        .padding(padding)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

extension View {
    func cardSurface(radius: CGFloat = 12) -> some View {
        background(AikuColor.surface, in: RoundedRectangle(cornerRadius: radius))
            .clipShape(RoundedRectangle(cornerRadius: radius))
            .overlay(RoundedRectangle(cornerRadius: radius).strokeBorder(AikuColor.border))
    }
}

struct RowDivider: View {
    var body: some View {
        Rectangle().fill(AikuColor.divider).frame(height: 1)
    }
}

struct CardList<Data: RandomAccessCollection, Row: View>: View where Data.Element: Identifiable {
    let items: Data
    @ViewBuilder let row: (Data.Element) -> Row

    init(_ items: Data, @ViewBuilder row: @escaping (Data.Element) -> Row) {
        self.items = items
        self.row = row
    }

    var body: some View {
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

struct IconTile: View {
    let icon: AppIcon
    var tone: Tone = .info
    var size: CGFloat = 40
    var radius: CGFloat = 10

    init(_ icon: AppIcon, tone: Tone = .info, size: CGFloat = 40, radius: CGFloat = 10) {
        self.icon = icon
        self.tone = tone
        self.size = size
        self.radius = radius
    }

    var body: some View {
        icon.image
            .font(.system(size: size * 0.48, weight: .regular))
            .foregroundStyle(tone.foreground)
            .frame(width: size, height: size)
            .background(tone.background, in: RoundedRectangle(cornerRadius: radius))
            .accessibilityHidden(true)
    }
}

struct ProductThumb: View {
    var size: CGFloat = 44
    var label: String?

    init(size: CGFloat = 44, label: String? = nil) {
        self.size = size
        self.label = label
    }

    var body: some View {
        AppIcon.photo.image
            .font(.system(size: size * 0.42))
            .foregroundStyle(AikuColor.faint)
            .frame(width: size, height: size)
            .background(AikuColor.bg, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.border))
            .accessibilityLabel(label ?? "Product photo")
    }
}

struct Avatar: View {
    let initials: String
    var size: CGFloat = 44

    init(_ initials: String, size: CGFloat = 44) {
        self.initials = initials
        self.size = size
    }

    var body: some View {
        Text(initials)
            .font(.aiku(size * 0.34, .bold))
            .foregroundStyle(AikuColor.navAccent)
            .frame(width: size, height: size)
            .background(AikuColor.navAct, in: Circle())
            .accessibilityHidden(true)
    }
}

struct ListRow<Leading: View, Trailing: View>: View {
    let title: String
    var subtitle: String?
    var chips: [Chip] = []
    var showsChevron = false
    var action: (() -> Void)?
    @ViewBuilder var leading: Leading
    @ViewBuilder var trailing: Trailing

    init(
        _ title: String,
        subtitle: String? = nil,
        chips: [Chip] = [],
        showsChevron: Bool = false,
        action: (() -> Void)? = nil,
        @ViewBuilder leading: () -> Leading,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.title = title
        self.subtitle = subtitle
        self.chips = chips
        self.showsChevron = showsChevron
        self.action = action
        self.leading = leading()
        self.trailing = trailing()
    }

    var body: some View {
        if let action {
            Button(action: action) { label }
                .buttonStyle(.plain)
        } else {
            label
        }
    }

    private var label: some View {
        HStack(spacing: 12) {
            leading
            VStack(alignment: .leading, spacing: 3) {
                FlowLayout(spacing: 8, lineSpacing: 4) {
                    Text(title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    ForEach(chips, id: \.self) { chip in
                        StatusChip(chip)
                    }
                }
                if let subtitle {
                    Text(subtitle)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .multilineTextAlignment(.leading)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            trailing
            if showsChevron {
                AppIcon.chevronRight.image
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(AikuColor.faint)
                    .accessibilityHidden(true)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
        .contentShape(Rectangle())
    }
}

extension ListRow where Leading == IconTile, Trailing == EmptyView {
    init(
        _ title: String,
        subtitle: String? = nil,
        icon: AppIcon,
        iconTone: Tone = .info,
        chips: [Chip] = [],
        showsChevron: Bool = false,
        action: (() -> Void)? = nil
    ) {
        self.init(title, subtitle: subtitle, chips: chips, showsChevron: showsChevron, action: action) {
            IconTile(icon, tone: iconTone)
        } trailing: {
            EmptyView()
        }
    }
}

extension ListRow where Leading == IconTile {
    init(
        _ title: String,
        subtitle: String? = nil,
        icon: AppIcon,
        iconTone: Tone = .info,
        chips: [Chip] = [],
        showsChevron: Bool = false,
        action: (() -> Void)? = nil,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.init(title, subtitle: subtitle, chips: chips, showsChevron: showsChevron, action: action) {
            IconTile(icon, tone: iconTone)
        } trailing: {
            trailing()
        }
    }
}

extension ListRow where Leading == EmptyView, Trailing == EmptyView {
    init(
        _ title: String,
        subtitle: String? = nil,
        chips: [Chip] = [],
        showsChevron: Bool = false,
        action: (() -> Void)? = nil
    ) {
        self.init(title, subtitle: subtitle, chips: chips, showsChevron: showsChevron, action: action) {
            EmptyView()
        } trailing: {
            EmptyView()
        }
    }
}

extension ListRow where Leading == EmptyView {
    init(
        _ title: String,
        subtitle: String? = nil,
        chips: [Chip] = [],
        showsChevron: Bool = false,
        action: (() -> Void)? = nil,
        @ViewBuilder trailing: () -> Trailing
    ) {
        self.init(title, subtitle: subtitle, chips: chips, showsChevron: showsChevron, action: action) {
            EmptyView()
        } trailing: {
            trailing()
        }
    }
}

struct StatTile: View {
    let value: String
    let label: String
    var tone: Tone?
    var action: (() -> Void)?

    init(_ value: String, label: String, tone: Tone? = nil, action: (() -> Void)? = nil) {
        self.value = value
        self.label = label
        self.tone = tone
        self.action = action
    }

    var body: some View {
        if let action {
            Button(action: action) { content }
                .buttonStyle(.plain)
        } else {
            content
        }
    }

    private var content: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(value)
                .font(.aiku(22, .bold))
                .foregroundStyle(tone?.foreground ?? AikuColor.text)
                .lineLimit(1)
                .minimumScaleFactor(0.7)
            Text(label)
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .multilineTextAlignment(.leading)
        }
        .padding(12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface(radius: 10)
        .contentShape(Rectangle())
    }
}

struct StatGrid<Content: View>: View {
    var columns = 3
    @ViewBuilder var content: Content

    init(columns: Int = 3, @ViewBuilder content: () -> Content) {
        self.columns = columns
        self.content = content()
    }

    var body: some View {
        LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 8, alignment: .top), count: columns), spacing: 8) {
            content
        }
    }
}

struct SectionHeading: View {
    let title: String
    var detail: String?
    var actionTitle: String?
    var action: (() -> Void)?

    init(_ title: String, detail: String? = nil, actionTitle: String? = nil, action: (() -> Void)? = nil) {
        self.title = title
        self.detail = detail
        self.actionTitle = actionTitle
        self.action = action
    }

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            Text(title.uppercased())
                .font(.aiku(13, .bold))
                .tracking(0.8)
                .foregroundStyle(AikuColor.text3)
                .accessibilityAddTraits(.isHeader)
            Spacer(minLength: 8)
            if let detail {
                Text(detail)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            if let actionTitle, let action {
                Button(action: action) {
                    Text(actionTitle)
                        .font(.aiku(13, .semibold))
                        .foregroundStyle(AikuColor.link)
                }
                .buttonStyle(.plain)
            }
        }
        .padding(.horizontal, 4)
    }
}

struct KeyValueRow: View {
    let label: String
    let value: String
    var valueTone: Tone?

    init(_ label: String, _ value: String, valueTone: Tone? = nil) {
        self.label = label
        self.value = value
        self.valueTone = valueTone
    }

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(label)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
            Spacer(minLength: 8)
            Text(value)
                .font(.aiku(14, .semibold))
                .foregroundStyle(valueTone?.foreground ?? AikuColor.text)
                .multilineTextAlignment(.trailing)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(minHeight: 44)
    }
}

struct ActivityRow: View {
    let icon: AppIcon
    var tone: Tone = .success
    let title: String
    var detail: String?
    let meta: String

    init(icon: AppIcon, tone: Tone = .success, title: String, detail: String? = nil, meta: String) {
        self.icon = icon
        self.tone = tone
        self.title = title
        self.detail = detail
        self.meta = meta
    }

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            icon.image
                .font(.system(size: 14, weight: .semibold))
                .foregroundStyle(tone.foreground)
                .frame(width: 32, height: 32)
                .background(tone.background, in: Circle())
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                (Text(title).fontWeight(.semibold) + Text(detail.map { " · \($0)" } ?? ""))
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text)
                Text(meta)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(14)
    }
}

struct FlowLayout: Layout {
    var spacing: CGFloat = 8
    var lineSpacing: CGFloat = 8

    func sizeThatFits(proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) -> CGSize {
        let rows = arrange(subviews: subviews, width: proposal.width ?? .infinity)
        let width = rows.map(\.width).max() ?? 0
        let height = rows.map(\.height).reduce(0, +) + lineSpacing * CGFloat(max(rows.count - 1, 0))
        return CGSize(width: proposal.width.map { min($0, width) } ?? width, height: height)
    }

    func placeSubviews(in bounds: CGRect, proposal: ProposedViewSize, subviews: Subviews, cache: inout ()) {
        var y = bounds.minY
        for row in arrange(subviews: subviews, width: bounds.width) {
            var x = bounds.minX
            for index in row.indices {
                let size = subviews[index].sizeThatFits(ProposedViewSize(width: bounds.width, height: nil))
                subviews[index].place(
                    at: CGPoint(x: x, y: y + (row.height - size.height) / 2),
                    proposal: ProposedViewSize(width: min(size.width, bounds.width), height: size.height)
                )
                x += size.width + spacing
            }
            y += row.height + lineSpacing
        }
    }

    private struct Row {
        var indices: [Int] = []
        var width: CGFloat = 0
        var height: CGFloat = 0
    }

    private func arrange(subviews: Subviews, width: CGFloat) -> [Row] {
        var rows: [Row] = []
        var current = Row()
        for index in subviews.indices {
            let size = subviews[index].sizeThatFits(ProposedViewSize(width: width, height: nil))
            let needed = current.indices.isEmpty ? size.width : current.width + spacing + size.width
            if needed > width, !current.indices.isEmpty {
                rows.append(current)
                current = Row()
            }
            current.width = current.indices.isEmpty ? size.width : current.width + spacing + size.width
            current.height = max(current.height, size.height)
            current.indices.append(index)
        }
        if !current.indices.isEmpty {
            rows.append(current)
        }
        return rows
    }
}
