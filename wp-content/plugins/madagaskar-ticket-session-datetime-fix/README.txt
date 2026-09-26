Madagaskar Ticket Session Datetime Fix
=====================================

Purpose
-------
This plugin fixes Tickera Ticket Designer PDF tickets when the Date & Time
field prints the first/default event session instead of the WooCommerce product
session purchased by the customer.

Install
-------
1. WordPress Admin > Plugins > Add New > Upload Plugin.
2. Upload Madagaskar_PDF_Bilet_Seans_Saati_Duzeltmesi_FINAL.zip.
3. Activate "Madagaskar Ticket Session Datetime Fix".
4. Keep the existing Ticket Designer field as dataField=event_datetime.

Test
----
Create or download test tickets from the same event for:
- 12:00
- 14:00
- 16:00

Expected result:
- 12:00 ticket prints 12:00 - 13:00
- 14:00 ticket prints 14:00 - 15:00
- 16:00 ticket prints 16:00 - 17:00

Notes
-----
The plugin does not change QR codes, ticket numbers, payment logic, stock,
Tickera templates, or WooCommerce products. It only corrects the ticket data
sent to the PDF renderer.

Verified
--------
Confirmed on 26 September 2026 with a Bolu 14:00 ticket:
TARİH & SAAT printed as 27 Eylül 2026 14:00 - 15:00.
