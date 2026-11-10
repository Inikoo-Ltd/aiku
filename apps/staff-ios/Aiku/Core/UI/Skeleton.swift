import SwiftUI

struct SkeletonBlock: View {
    var width: CGFloat?
    var widthFraction: CGFloat = 1
    let height: CGFloat
    var cornerRadius: CGFloat = 6

    var body: some View {
        if let width {
            shape.frame(width: width, height: height)
        } else {
            GeometryReader { proxy in
                shape.frame(width: proxy.size.width * widthFraction)
            }
            .frame(height: height)
        }
    }

    private var shape: some View {
        RoundedRectangle(cornerRadius: cornerRadius)
            .fill(AikuColor.divider)
            .shimmering()
    }
}

struct SkeletonListCard: View {
    var rows = 3

    var body: some View {
        VStack(spacing: 12) {
            ForEach(0..<rows, id: \.self) { _ in
                HStack(spacing: 12) {
                    SkeletonBlock(width: 40, height: 40, cornerRadius: 8)
                    VStack(alignment: .leading, spacing: 8) {
                        SkeletonBlock(widthFraction: 0.6, height: 12)
                        SkeletonBlock(widthFraction: 0.4, height: 10)
                    }
                }
            }
        }
        .padding(16)
        .card()
        .loadingAccessibility()
    }
}

struct SkeletonStatCard: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            SkeletonBlock(widthFraction: 0.5, height: 12)
            SkeletonBlock(widthFraction: 0.35, height: 26)
            SkeletonBlock(height: 8, cornerRadius: 4)
        }
        .padding(16)
        .card()
        .loadingAccessibility()
    }
}

private struct Shimmer: ViewModifier {
    @Environment(\.accessibilityReduceMotion) private var reducesMotion
    @State private var phase: CGFloat = -1

    func body(content: Content) -> some View {
        content
            .overlay {
                if !reducesMotion {
                    GeometryReader { proxy in
                        LinearGradient(colors: [.clear, AikuColor.border, .clear], startPoint: .leading, endPoint: .trailing)
                            .frame(width: proxy.size.width)
                            .offset(x: proxy.size.width * phase)
                    }
                    .mask(content)
                }
            }
            .onAppear {
                guard !reducesMotion else { return }
                withAnimation(.linear(duration: 1.4).repeatForever(autoreverses: false)) {
                    phase = 1
                }
            }
    }
}

extension View {
    func shimmering() -> some View {
        modifier(Shimmer())
    }

    func loadingAccessibility() -> some View {
        accessibilityElement(children: .ignore)
            .accessibilityLabel("Loading")
    }
}
