# Campaign event poster — 2026-10-05

Snippet124 updated, active=true/code_error=null. Each campaign card reads the event's hero_attachment_id, short_description and stripped 65-word long_description excerpt. Missing long copy uses generic hayvansız/aile-friendly statement. No hardcoded image IDs in source; no event/product/media records changed. Poster uses wp_get_attachment_image large, responsive srcset and natural aspect ratio with max560px CSS.

PHP lint,90 regression and jsdom passed. Live BMS and sagliksendenizli cards contain Denizli attachment2409, Denizli title, show introduction including akrobasi/rola bola/hula hop/paten/jonglörlük/palyaço, date and venue, and adult data-price475. İzmir TEST card uses its own attachment2384 and İzmir introduction, regular adult600. Neither city uses the other's poster. No order/payment submitted; checkout code unchanged. Actual browser visual screenshot was not captured; rendered card HTML, source media metadata and image dimensions/srcset verified.

PR144 stacked on143. Rollback snippet124 to PR143 source. Prefilled distribution links /kampanya/?kod=bms and /kampanya/?kod=sagliksendenizli open scoped event directly.
