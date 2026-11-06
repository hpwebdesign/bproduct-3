# Quick Product Manager v1.1.5.4

Compatibility: OpenCart 3.0.3.x (tested on 3.0.3.9, PHP 8.3, MariaDB 10.11)

## What's new

**Product list**
- Choose which columns the list shows. A new **Columns** tab in the module settings turns Model, Categories, Manufacturer, Price, Quantity and Date added on or off.
- The quick stats now sit on the same row as the Add button, and the header no longer carries a logo, subtitle or version badge.

**Category Manager**
- The list shows each category's image instead of the folder icon (the folder stays for categories without an image).
- **Extra parents:** show one category under several parents. Works with the Multiple Parent Category extension.
- Same stats and header layout as the product list.

**Editors**
- **Image popover** for category and product images: select a file, drag and drop, download from a URL, open the file manager, or clear. It is built in, and the popover of the hpmm module is used when that module is installed.
- **Complete SEO support.** When Complete SEO is active, the General tab shows Keyword and SEO H1, H2 and H3 (plus Image alt and Image title for products), above the meta title, per language.
- Attribute values are saved as bare text and edited in 2-line fields.
- Dates are typed as `dd-mm-yyyy` (24-10-1991 is formatted as you type). New specials start without dates.

**Settings**
- New settings page layout with sidebar tabs, a floating save bar and an unsaved-changes notice.

## Fixes
- With Complete SEO active, the keyword is now passed on every save, so it is no longer lost.
- With Complete SEO active, saving a product no longer clears its SEO H1, H2 and H3.

## Upgrade notes
1. Extensions > Installer: upload `bproduct.v-1.1.5.4.ocmod.zip` (full package, safe to install over an older version).
2. Extensions > Modifications: **Refresh**.
3. Hard-refresh the manager page (Ctrl+F5).
4. Optional: open the module settings, check the new **Columns** tab and save.

New files: `catalog/bmedia` (image upload and URL download) and `view/javascript/bproduct/hpmm_common.js`. No database changes.

## Good to know
- Attribute values lose their HTML when saved here, so a link inside an attribute becomes plain text.
- The image upload size follows Settings > Server > Max file size (OpenCart's default is 300 KB).
- Complete SEO and Multiple Parent Category are optional. The related fields appear only when they are active.
