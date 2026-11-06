# Quick Product Manager – Product and Category Management for OpenCart 3

Managing a growing catalog in OpenCart often means opening one page after another. Quick Product Manager keeps the list, the filters and the editor on a single screen, so everyday changes take fewer steps.

## How it works

The product and category lists are replaced by an AJAX-based manager. Click a row and the editor opens in a side drawer, while the list stays where you left it.

When you save, only the tabs you changed are sent, and the data is written through the standard OpenCart models. Your user permissions apply as usual. If you would like the original lists back, you can turn the module off or open the classic list at any time.

## Product Manager

- Search by name or model, filter by status and choose the page size.
- Filter by category (including subcategories), manufacturer, model, store, SKU, UPC, EAN, JAN, ISBN, MPN and Requires Shipping. Active filters are shown as chips you can remove one by one.
- Sort by name or by date added, and choose which columns the list shows (Model, Categories, Manufacturer, Price, Quantity, Date added) in the settings.
- Edit the SEO URL directly in the list, generate it from the name, copy it or open it on your storefront.
- Switch the status per row, or use bulk actions: Enable, Disable, add to category, set manufacturer, Clone and Delete.
- **Quick Add** takes a name, model, categories, manufacturer, image and status. Everything else gets safe defaults, and you can save, save and add another, or save and edit.

## Product editor

The drawer follows the default product form: General, Data, Category Links, Related Products, Other Links, Attribute, Option, Discount, Special, Image, Reward Points, SEO and Design. You decide the tab order and which optional tabs are shown.

- Descriptions, SEO keywords and layouts for every language and store.
- A rich text editor for descriptions.
- An image popover: select a file, drag and drop, download from a URL, or open the file manager.
- Dates are typed as dd-mm-yyyy, and new specials start without dates.
- A "Full product form" button opens the default form, for fields added by other extensions.

## Category Manager

- A category list with the full path, category image, SEO URL, product count and status toggle. Every column can be sorted.
- Search by name or display name, filter by status or parent, and use bulk actions: Enable, Disable, Move to parent, show or hide in the top menu.
- An editor drawer with General, Data, SEO and Design: parent, sort order, columns, image, stores and filters.
- **Display Name** is a separate, per-language name shown to customers in menus, breadcrumbs and category pages, while the category name stays for admin use.
- **Extra parents** shows one category under several parents (works with the Multiple Parent Category extension).
- Quick Add for new categories.

## Barcode Manager

Generate a Code128 barcode from the Model or SKU in the product editor and download it as a PNG. When the Barcode Manager is on, Model and SKU are checked for uniqueness. The barcode can also be shown on the product page of the default and Barik themes.

## Works with other extensions

- **Complete SEO:** when it is active, Keyword, SEO H1, H2 and H3 (plus Image alt and Image title for products) appear in the editors and are saved with the record.
- **Multiple Parent Category:** extra parents appear in the category editor.
- **hpmm media manager:** if it is installed, its image popover is used. Otherwise the built-in popover is used.

## Good to know

- Data that another extension stores outside the standard product fields can be reset when a product is saved from the drawer. For those products the "Full product form" is the better choice.
- The Recurring tab is not part of the drawer. Use the full product form for it.
- To save a product, a name and a meta title are needed in every active language.
- The category tree browser is replaced by search, and only the en-gb language file is included.

## Compatibility

- OpenCart 3.0.3.x
- Tested on OpenCart 3.0.3.9, PHP 8.3 and MariaDB 10.11
- The Display Name feature uses OCMOD, so refresh your modifications after installing.

## Installation

1. Extensions > Installer: upload the package.
2. Extensions > Modifications: click Refresh.
3. Extensions > Extensions > Modules: install Quick Product Manager.
4. Click Edit, set Status to Enabled and save.
5. Optional: in the module settings, turn on the Category Manager to replace the default category list as well.

## Frequently asked questions

**Does it change my products?**  
Only when you save an edit or use an action such as the status switch, a bulk action or Quick Add. The module reads and writes through the standard OpenCart models.

**Can I still use the original product form?**  
Yes. Use the "Full product form" button, or open the classic list with one click.

**What happens if I uninstall it?**  
Your catalog stays as it is. Only the Display Name table is kept, in case you install the module again.

**Does it work with more than one language or store?**  
Yes, in descriptions, SEO URLs and layouts.

## Changelog

- **1.1.5.x** Column settings, category images in the list, bare-text attributes, dd-mm-yyyy dates, case-insensitive search, drawer width setting.
- **1.1.4.0** Built-in image popover, hpmm popover supported.
- **1.1.2.x** Keyword, SEO H1–H3, Image alt and Image title fields for Complete SEO.
- **1.1.0.9** Extra parents in the Category Manager.
- **1.1.0.0** Barcode Manager, Category Manager, Display Name.
- **1.0.0.0** First release.

## Support

Questions and feedback are welcome through Bariklabs (bariklabs.com).
