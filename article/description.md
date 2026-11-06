# Quick Product Manager – Faster Product & Category Management for OpenCart 3

## Pain points

- Every product edit means a page reload and a trip back to the list.
- Changing many products means repeating the same steps one by one.
- Finding products by SKU, manufacturer or category takes several steps in the default list.

## What you gain

- List, filters and editor on one screen. The editor opens in a side drawer.
- Bulk actions for status, category, manufacturer, clone and delete.
- Filters for category, manufacturer, model, store, SKU, UPC, EAN, JAN, ISBN and MPN.
- Category display names, extra parents and SEO fields in the same drawer.

## How it works

- Replaces the product and category lists with an AJAX-based manager.
- Click a row to open the editor in a side drawer.
- Saving sends only the tabs you changed.
- Data is written through the standard OpenCart models, and user permissions apply as usual.
- Turn the module off, or open the classic list, to get the original lists back.

## Product Manager

- Search by name or model, filter by status, choose the page size.
- Filters: category (with subcategories), manufacturer, model, store, SKU, UPC, EAN, JAN, ISBN, MPN, Requires Shipping.
- Active filters shown as removable chips.
- Sort by name or date added.
- Choose the list columns in the settings: Model, Categories, Manufacturer, Price, Quantity, Date added.
- SEO URL: edit in the list, generate, copy, open on the storefront.
- Status switch per row.
- Bulk actions: Enable, Disable, add to category, set manufacturer, Clone, Delete.
- **Quick Add:** name, model, categories, manufacturer, image, status. Other fields get defaults.

## Product editor

- Tabs follow the default product form: General, Data, Category Links, Related Products, Other Links, Attribute, Option, Discount, Special, Image, Reward Points, SEO, Design.
- Tab order and optional tabs are set in the settings.
- Descriptions, SEO keywords and layouts per language and store.
- Rich text editor for descriptions.
- Image popover: select a file, drag and drop, URL, file manager.
- Dates typed as dd-mm-yyyy. New specials start without dates.
- "Full product form" button opens the default form for fields from other extensions.

## Category Manager

- List with full path, image, SEO URL, product count and status switch. All columns sortable.
- Search by name or display name. Filter by status or parent.
- Bulk actions: Enable, Disable, Move to parent, show or hide in the top menu.
- Editor drawer: General, Data, SEO, Design.
- **Display Name:** a per-language name shown to customers in menus, breadcrumbs and category pages.
- **Extra parents:** one category under several parents (Multiple Parent Category).
- Quick Add for new categories.

## Barcode Manager

- Code128 barcode from Model or SKU, downloadable as PNG.
- Model and SKU checked for uniqueness while the Barcode Manager is on.
- Optional barcode on the product page of the default and Barik themes.

## Works with

- **Complete SEO:** Keyword, SEO H1–H3, and for products Image alt and Image title appear in the editors.
- **Multiple Parent Category:** extra parents in the category editor.
- **hpmm media manager:** its image popover is used when installed, otherwise the built-in one.

## Good to know

- Data that another extension stores outside the standard product fields can be reset when a product is saved from the drawer. Use the "Full product form" for those products.
- The Recurring tab is not in the drawer.
- A product needs a name and a meta title in every active language to be saved.
- The category tree browser is replaced by search.
- Only the en-gb language file is included.

## Compatibility

- OpenCart 3.0.3.x.
- Tested on OpenCart 3.0.3.9, PHP 8.3, MariaDB 10.11.
- Display Name uses OCMOD: refresh modifications after installing.

## Installation

1. Extensions > Installer: upload the package.
2. Extensions > Modifications: Refresh.
3. Extensions > Extensions > Modules: install Quick Product Manager.
4. Edit, set Status to Enabled, save.
5. Optional: turn on the Category Manager in the module settings.

## FAQ

- **Does it change my products?** Only when you save an edit or use an action such as the status switch, a bulk action or Quick Add.
- **Can I still use the original form?** Yes, through the "Full product form" button or the classic list.
- **What happens on uninstall?** Your catalog stays as it is. The Display Name table is kept.
- **Multiple languages or stores?** Yes, in descriptions, SEO URLs and layouts.

## Changelog

- **1.1.5.x** Column settings, category images in the list, bare-text attributes, dd-mm-yyyy dates, case-insensitive search, drawer width setting.
- **1.1.4.0** Built-in image popover, hpmm popover supported.
- **1.1.2.x** Keyword, SEO H1–H3, Image alt and Image title for Complete SEO.
- **1.1.0.9** Extra parents in the Category Manager.
- **1.1.0.0** Barcode Manager, Category Manager, Display Name.
- **1.0.0.0** First release.

## Support

- Questions and feedback: Bariklabs (bariklabs.com).
