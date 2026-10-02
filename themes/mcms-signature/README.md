# mCMS Signature

The second first-party theme. Activate **mCMS Signature** in Administration → Themes.
Activation preserves the current Home selection and module content. Menu assignments
are independent per theme, with the same header/footer locations as mCMS Default.
Without a header assignment, public enabled News, Galleries and Feedback routes form
a small automatic navigation menu. Assigned menus take precedence.

Warm paper, dark ink and a violet accent; local system fonts and a code-drawn mCMS
mark, without external fonts, trackers or stock imagery. All current logical templates
are supported, including `galleries.embed`. Home shows up to three current News articles
and albums through their public APIs, when those modules are enabled. A selected Home
page retains priority and uses the reading layout.

- Native disclosure navigation works without JavaScript, with current-page indicators.
- Skip link, visible keyboard focus, readable text widths, 44–48px navigation controls.
- Responsive cards, forms and typography; horizontally scrollable content tables.
- Gallery dialog supports previous/next, arrow keys, Escape, captions, loading errors
  and focus restoration. Original-image links remain usable without JavaScript.
- English/Russian text, reduced-motion support and explicit empty states.

Accessibility design references: [WCAG 2.2 quick reference](https://www.w3.org/WAI/WCAG22/quickref/)
and [native dialog behavior](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog).
These are design targets; this version has not received a complete WCAG conformance audit.
