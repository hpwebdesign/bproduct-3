# Quick Product Manager – Fast AJAX Product & Category Manager for OpenCart 3

**Stop opening a new page for every product. Find, filter, edit and add products and categories from one screen.**

Quick Product Manager replaces the default OpenCart product and category lists with a fast, AJAX-based manager. Search, filter, edit and bulk-update your catalog without page reloads, then jump to the full OpenCart form only when you need it.

## Why you'll like it

- **One screen for the whole catalog.** The list, the filters and the editor live together. Click a row and the editor opens in a side drawer.
- **Edit only what changed.** Only the tabs you touched are sent to the server, and one Save button saves them all. A dot marks unsaved tabs.
- **Safe by design.** Saving goes through the standard OpenCart models, so your existing extensions and permissions keep working. No extra permission setup.
- **Easy to switch off.** Disable the module and the default OpenCart lists come back at once. The classic list is also always available with one click.

## Product Manager

- Search by name or model, filter by status, choose the page size.
- Collapsible filters: category (with subcategories), manufacturer, model, store, SKU, UPC, EAN, JAN, ISBN, MPN and Requires Shipping.
- Active filters are shown as chips you can remove one by one.
- Sort by name or by date added.
- Edit the SEO URL right in the list, generate it from the name, copy it or open it on your storefront.
- Toggle status per row.
- Bulk actions: Enable, Disable, add to category, set manufacturer, Clone, Delete.
- **Quick Add:** name, model, categories, manufacturer, image and status. Everything else gets safe defaults. Choose Save, Save & add another, or Save & edit.

## Product editor

The drawer follows the default product form: General, Data, Category Links, Related Products, Other Links, Attribute, Option, Discount, Special, Image, Reward Points, SEO and Design. You choose the tab order and which optional tabs are shown.

- Multi-language and multi-store: descriptions, SEO keywords and layouts.
- Rich text editor for descriptions.
- Image popover: select a file, drag and drop, download from a URL, or open the file manager.
- A "Full product form" button opens the default form for fields added by other extensions.

## Category Manager

- AJAX category list with the full path, SEO URL, product count and status toggle. Every column is sortable.
- Search, status filter, parent filter, bulk Enable, Disable, Move to parent, show or hide in the top menu.
- Editor drawer: General, Data, SEO and Design, including parent, sort order, columns, image, stores and filters.
- **Display Name:** a separate, per-language name shown to customers in menus, breadcrumbs and category pages, while the category name stays for admin use.
- **Extra parents:** show one category under several parents (works with the Multiple Parent Category extension).
- Quick Add for new categories.

## Barcode Manager

Generate a Code128 barcode from the Model or SKU in the product editor and download it as a PNG. Model and SKU are checked for uniqueness. You can also show the barcode on the product page of the default and Barik themes.

## Works with other extensions

- **Complete SEO:** when it is active, SEO H1, H2, H3, Keyword, Image alt and Image title appear in the editors and are saved correctly.
- **Multiple Parent Category:** extra parents appear in the category editor.
- **hpmm media manager:** if it is installed, its image popover is used. Otherwise the built-in popover is used.

## Requirements and compatibility

- OpenCart 3.0.3.x
- PHP 7.4 or newer (tested on PHP 8.3)
- MySQL or MariaDB (tested on MariaDB 10.11)
- The Display Name feature uses OCMOD, so refresh your modifications after installing.

## Installation

1. Extensions > Installer: upload the package.
2. Extensions > Modifications: click Refresh.
3. Extensions > Extensions > Modules: install Quick Product Manager.
4. Click Edit, set Status to Enabled and save.

## FAQ

**Will it change my products?**  
Only when you save an edit. The module reads and writes through the standard OpenCart models.

**Can I still use the original product form?**  
Yes. Use the "Full product form" button, or open the classic list with one click.

**What if I uninstall it?**  
Your catalog is untouched. Only the Display Name table is kept, in case you reinstall.

**Does it support more than one language or store?**  
Yes, in descriptions, SEO URLs and layouts.

## Changelog

- **1.1.4.0** Built-in image popover, hpmm popover supported.
- **1.1.2.x** Keyword, SEO H1–H3, Image alt and Image title fields for Complete SEO.
- **1.1.0.9** Extra parents in the Category Manager.
- **1.1.0.0** Barcode Manager, Category Manager, Display Name.
- **1.0.0.0** First release.

## Support

Documentation and tickets are available from the module settings page.
