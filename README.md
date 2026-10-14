# Quick Product Manager (bproduct)

A fast, AJAX-based replacement for the default OpenCart product list.
Find, filter, edit and add products from one page, without leaving the list.

- **Version:** 2.0.0
- **Author:** Bariklabs
- **Compatibility:** OpenCart 3.0.3.x (admin, Twig, OCMOD)
- **Tested on:** OpenCart 3.0.3.9, PHP 8.3, MariaDB 10.11

## Features

**List**
- Search by name or model, status filter (All / Enabled / Disabled), page size, pager.
- Sort by product name (A–Z / Z–A) or date added.
- Collapsible filter panel with always-visible active-filter chips:
  category (with subcategories), manufacturer, model, store, and field filters
  (SKU, UPC, EAN, JAN, ISBN, MPN, Requires Shipping, with an Exact option).
  A field filter also adds that field as a column.
- Inline SEO URL: edit, generate, copy, open in storefront.
- Status toggle per row.
- Bulk bar: Enable, Disable, Add to category, Set manufacturer.
  Clone and Delete buttons work on the selection.
- Loading animation on every filter, sort and page change.

**Edit drawer** (default tab order follows the product form; order and visibility are configurable in Settings)
General, Data, Category Links, Related Products, Other Links
(manufacturer, stores, filters, downloads), Attribute, Option, Discount,
Special, Image, Reward Points, SEO, Design.

- Only the tabs you changed are sent to the server. One Save button saves them all.
- A dot marks tabs with unsaved changes. Closing the drawer asks before discarding.
- Multi-language and multi-store aware (descriptions, SEO keywords, layouts).
- Images use the standard OpenCart file manager.
- "Full product form" button opens the default form for fields added by
  third-party extensions.

**Quick Add**
Name, model, categories, manufacturer, image and status. Everything else gets
safe defaults. Save, Save & add another, or Save & edit.

## Installation

1. Go to **Extensions > Installer** and upload `bproduct.ocmod.zip`.
   (If your server needs FTP for the installer, copy the contents of `upload/`
   to the OpenCart root manually instead.)
2. Go to **Extensions > Modifications** and click **Refresh**.
3. Go to **Extensions > Extensions**, choose **Modules**, find
   **Quick Product Manager** and click **Install**.
4. Click **Edit**, set **Status** to **Enabled** and save.

The module is disabled after installation. While it is disabled (or not
installed), the default OpenCart product list is used.

## Settings

| Setting | Description |
|---|---|
| Status | Turns the module on or off. |
| Products per page | Initial page size: 5, 10, 25, 50 or 100. |
| Default sort | Name A–Z, Name Z–A, Newest first, Oldest first. |
| Status of new products | Status given to products created with Quick Add. |
| Product Editor Tabs | Drag to change the order of the edit-drawer tabs and show or hide them. General, Data, Category Links, SEO and Image are always shown (they can be reordered but not hidden). Related Products, Other Links, Attribute, Option, Discount, Special, Reward Points and Design can be hidden. |

The settings page saves with AJAX (button or Ctrl+S) and shows the result in an
alert above the tabs, without reloading. Hidden tabs are removed from the edit
drawer and from the per-product Manage menu, so their data is not loaded or
saved from the drawer. Use the **Full product form** to edit them.

## How it works

- The OCMOD adds a short hook at the start of `index()` in
  `admin/controller/catalog/product.php`. When the module is enabled, it returns
  `new Action('catalog/bproduct')`.
- Saving, adding, deleting and cloning use the default `catalog/product` model
  (`editProduct`, `addProduct`, `deleteProduct`, `copyProduct`).
  The module model only adds list queries, statistics and bulk helpers.
- AJAX requests go through `catalog/product&bp=<action>`, so the default
  `catalog/product` permission is reused. No extra permission is needed.
- The default list is still available at `index.php?route=catalog/product&classic=1`.
  The choice is kept in the session. A lightning button in the classic list
  (`classic=0`) switches back.

## Files

```
install.xml
upload/admin/
  controller/catalog/bproduct.php
  controller/extension/module/bproduct.php
  model/catalog/bproduct.php
  language/en-gb/catalog/bproduct.php
  language/en-gb/extension/module/bproduct.php
  view/template/catalog/bproduct.twig
  view/template/extension/module/bproduct.twig
  view/stylesheet/bproduct/settings.css
```

OCMOD changes (all with `error="skip"`):
- `admin/controller/catalog/product.php`: hand-over hook and a `bp_status` template variable.
- `admin/view/template/catalog/product_list.twig`: button to return to the manager.

## Known limitations

- `editProduct` deletes and re-inserts all product relations. Data that a
  third-party extension stores outside the standard product fields can be lost
  when a product is saved here. Use the **Full product form** for those products.
- The Recurring tab is not included. Use the full product form.
- The category tree browser is replaced by category search.
- A product needs a name and meta title in every active language before it can
  be saved from the drawer.
- Slugs are not changed automatically when a product is renamed.
- Only the `en-gb` language file is included.

## Uninstall

1. Extensions > Extensions > Modules > Quick Product Manager > **Uninstall**.
2. Extensions > Installer: remove the package, or delete the files listed above.
3. Extensions > Modifications: **Refresh**.

## Demo Link

- Product Page Demo : https://demo09.bariklabs.com/admin/index.php?route=catalog/product&user_token=KST2PZwaS7crhuHStWdxLnMg2FgCktt7
- Backend Demo  : https://demo09.bariklabs.com/admin/index.php?route=extension/module/bproduct&user_token=KST2PZwaS7crhuHStWdxLnMg2FgCktt7