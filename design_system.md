# Design System Map

## Source
- Figma file key: `Q4qoKLXrnwwF1kRsYnDyt3`
- Audited root node: `696:4521` (`Index.html`)
- Key component sample nodes:
  - Language switcher: `628:305`
  - Mobile menu trigger: `694:1653`
  - Philosophy icon: `694:1322`
  - Card link: `636:9496`

## Inventory

| Category | Count | Source in Figma |
|---|---:|---|
| Colors | 6 | DS colors (Kant 1..6), synced to `css/tokens.css` |
| Spacing tokens | 9 | DS spacing scale, synced to `css/tokens.css` |
| Radius tokens | 0 | Not defined in current DS |
| Text styles | 7 | `H1/H2/H3/H4/Paragraph/Paragraph_Caps/Descriptor` |
| Components | 12+ | Header/Nav, Lang switcher, Mobile menu, Buttons, Card Link, Author Card, Perforations |
| Icons | 8+ | Arrow set, hand set, chevron, philosophy icon |

## Code Mapping
- Tokens: `css/tokens.css`
- Component styles: `css/components.css`
- DS showcase page: `design-system.html`
- Interactive behavior:
  - Language dropdown: `js/lang-switcher.js`
  - Mobile fullscreen menu: `js/mobile-menu.js`

## Sync Notes
- `design-system.html` is aligned with current implementation:
  - Dropdown `lang-switcher` with `EN/RU`
  - Mobile fullscreen menu structure and close control
  - Updated `card-link` structure with `.card-link__meta-action`
  - Added showcase for `icon-illustration--philosophy`
- Rule: new UI states should be added first in `css/components.css`, then mirrored in `design-system.html`.
