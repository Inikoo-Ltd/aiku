import SwiftUI
import Testing
@testable import Aiku

struct FontAwesomeIconTests {
    @Test("Every web icon case draws a Font Awesome glyph, the others stay SF Symbols")
    func iconsMapToGlyphs() {
        let webIcons = AppIcon.allCases.filter { $0.rawValue.hasPrefix("fa.") }

        #expect(webIcons.count == FontAwesomeGlyph.allCases.count)
        #expect(webIcons.allSatisfy { $0.fontAwesome != nil })
        #expect(AppIcon.search.fontAwesome == nil)
        #expect(AppIcon.faStackOverflow.fontAwesome == .stackOverflow)
    }

    @Test("Every glyph parses into a path that fills its viewport")
    func glyphsFitTheirViewport() {
        for glyph in FontAwesomeGlyph.allCases {
            let bounds = FontAwesomePaths.shared.path(for: glyph).boundingRect
            let viewport = CGRect(x: 0, y: 0, width: glyph.viewport, height: glyph.viewport)

            #expect(!bounds.isEmpty, "\(glyph.rawValue) is empty")
            #expect(viewport.insetBy(dx: -16, dy: -16).contains(bounds), "\(glyph.rawValue) spills out: \(bounds)")
            #expect(max(bounds.width, bounds.height) > glyph.viewport * 0.6, "\(glyph.rawValue) is too small: \(bounds)")
        }
    }

    @Test("Path data reads compact numbers, implicit commands and relative moves")
    func compactPathData() {
        let path = SVGPathData.path("M10 10h20v.5-.5l-5 5.5.5.5zm0 0H0V-10")
        let bounds = path.boundingRect

        #expect(abs(bounds.minX - 0) < 0.001)
        #expect(abs(bounds.maxX - 30) < 0.001)
        #expect(abs(bounds.minY - -10) < 0.001)
        #expect(abs(bounds.maxY - 16) < 0.001)
    }

    @Test("Arcs become curves that reach their end point")
    func arcs() {
        let half = SVGPathData.path("M0 0A10 10 0 0 1 20 0").boundingRect
        let flagsWithoutSpaces = SVGPathData.path("M0 0a10 10 0 0120 0").boundingRect

        #expect(abs(half.width - 20) < 0.01)
        #expect(abs(half.height - 10) < 0.05)
        #expect(abs(flagsWithoutSpaces.width - 20) < 0.01)
        #expect(abs(flagsWithoutSpaces.height - 10) < 0.05)
    }

    @Test("A glyph is scaled into the square it is drawn in")
    func shapeFitsItsRect() {
        let rect = CGRect(x: 0, y: 0, width: 24, height: 24)
        let bounds = FontAwesomeShape(glyph: .box).path(in: rect).boundingRect

        #expect(rect.insetBy(dx: -0.5, dy: -0.5).contains(bounds))
        #expect(bounds.width > 20)
    }
}
