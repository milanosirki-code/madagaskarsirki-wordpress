# Milano professional page redesign — 15 October 2026

Five existing live Milano pages redesigned in place: 6(sehirler),118(bilet-al),125(Karamürsel),126(Darıca),127(Derince). Existing URLs/status preserved. Original complete page sources saved before mutation in pages-before.json; final exact block markup in pages-after.json.

## Delivered design
Cream background, restrained navy/crimson/gold palette, Inter typography, consistent spacing and card borders. Theme presets inspected; brand colors use scoped custom variables because neutral theme presets cannot represent Milano identity.
City page: four chronological poster cards, dates, venue, session chips, visible three price categories, separate event detail and purchase links.
Three Kocaeli event pages: poster/information hero, clear sales badge, date/venue/address, direct city-specific ticket anchors, price cards, concise show/admission information.
Ticket page: first choose a city. CSS :target/:has shows only the matching native forms, rather than nine simultaneous forms. No default city is silently selected on generic /bilet-al/. Four city tabs include existing Yalova. Dynamic [milano_merkezi_bilet] shortcode block retained; generated forms, action, quantities, hidden canonical IDs, prices and server ownership verification unchanged. No custom JavaScript added.
Existing theme's important heading styles overrode initial CSS; fixed using explicit scoped heading appearance and inline important h1 typography. Darıca locative corrected to Darıca’da.

## Verification
Native WordPress.com pages.update successful for all five, no content warnings. All use registered core/group, core/html and existing core/shortcode blocks.
Desktop live view inspected: event poster, buttons, price cards and four-column catalog.
All four city tabs exercised: Karamürsel2, Yalova2, Darıca2, Derince3 visible correct session forms; unrelated forms hidden, server validations unchanged.
Darıca23October19:30 family1 submitted via new visual form to native central landing: correct Milano/Darıca, date/time,4people,1100TRY. Did not click final continuation/create cart/order/payment in this redesign test.
Temporary draft212 framed actual live event and ticket pages at390px outer iframe width, effective375px document viewport after scrollbar. Both scrollWidth375=clientWidth375 (no horizontal page overflow). Visible Derince forms339px; submit buttons301px; quantity inputs85x48px. Mobile screenshot visually inspected; no overlapping fields/buttons. Temporary draft212 moved to recoverable trash after testing.
Header, Yalova event detail page, homepage and other informational pages unchanged. Shared assembler//footer updated with only two copy replacements inviting city selection. Contact links and payment disclosure preserved. Backup footer-before.json and final footer-after.html recorded.

## Business state preserved
No central Madagaskar code, Woo products, Tickera events/templates, MDG/MMC capacities, orders, PayTR callback or payment credentials changed. Kocaeli sale state remains the authorized live state from prior task; no new paid E2E claim.
WordPress native revisions and GitHub original-source backup provide rollback. No plugins installed or deployed for design. Branch feat/milano-professional-pages-20261015 derives from existing reviewed project work.

## Evidence
Desktop and mobile screenshots visually inspected. Final live catalog screenshot milano-professional-final-1792087686105.jpg synchronized and opened successfully including updated footer. Draft PR #268 contains source backups.
