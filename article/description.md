# Quick Product Manager – Faster Product & Category Management for OpenCart 3
Manage your OpenCart products and categories faster from a single, streamlined interface. Edit products without navigating between pages, update multiple items at once, and access essential catalog information without the limitations of the default administration lists.

**Quick Product Manager** replaces the standard product and category lists with an AJAX-powered management interface, featuring inline controls, bulk actions, advanced filters, and a convenient side-drawer editor.

Spend less time managing your catalog and more time growing your store.

## Why Quick Product Manager?

Managing a growing product catalog through the default OpenCart interface can be time-consuming.

- **Too many page reloads:** Every product edit requires navigating away from the list and returning afterward.
- **Repetitive updates:** Updating multiple products means performing the same actions individually.
- **Limited product filtering:** Finding products by SKU, manufacturer, category, or other identifiers takes extra steps.
- **Fragmented category management:** Important category settings are spread across different fields and screens.

Quick Product Manager brings these workflows together in one place.

## Key Benefits

- **One-screen management:** Browse products, apply filters, and edit details without leaving the management page.
- **Convenient side-drawer editor:** Open any product or category directly from its list.
- **Powerful bulk actions:** Update product status, categories, manufacturers, and more in just a few clicks.
- **Advanced filtering:** Find products using multiple identifiers and catalog attributes.
- **Improved category management:** Manage display names, multiple parent categories, and SEO fields from one interface.
- **Flexible workflow:** Return to the original OpenCart lists or use the full product form whenever needed.

## How It Works

1. The standard product and category lists are replaced with an AJAX-based management interface.
2. Click any row to open its editor in a convenient side drawer.
3. Edit the information you need and save. Only modified tabs are submitted.
4. Data is saved through standard OpenCart models, while existing user permissions remain in effect.
5. Disable the module or access the classic lists to restore the original management interface.

## Product Manager

Take control of your product catalog with faster navigation, flexible filters, and powerful bulk management tools.

### Product Listing & Filters

- Search products by name or model.
- Filter by status and customize the number of products per page.
- Advanced filters for category (including subcategories), manufacturer, model, store, SKU, UPC, EAN, JAN, ISBN, MPN, and Requires Shipping.
- Active filters are displayed as removable chips.
- Sort products by name or date added.
- Customize visible columns through module settings: Model, Categories, Manufacturer, Price, Quantity, and Date Added.
- Manage SEO URLs directly from the list: edit, generate, copy, and open storefront links.
- Change product status instantly using the row-level status switch.

### Bulk Product Actions

Perform common catalog operations on multiple products at once:

- Enable or disable products.
- Assign products to categories.
- Set manufacturers.
- Clone products.
- Delete products.

### Quick Add

Create new products without opening the full product form.

Simply enter the essential information:

- Product name
- Model
- Categories
- Manufacturer
- Image
- Status

Other product fields are automatically populated with default values.

## Product Editor

Edit product information through a streamlined side-drawer interface.

### Complete Product Tabs

The editor follows the standard OpenCart product form structure:

- General
- Data
- Category Links
- Related Products
- Other Links
- Attributes
- Options
- Discounts
- Specials
- Images
- Reward Points
- SEO
- Design

Additional flexibility includes:

- Customize tab order and enable or disable optional tabs through module settings.
- Manage descriptions, SEO keywords, and layouts across multiple languages and stores.
- Built-in rich text editor for product descriptions.
- Convenient image popover with file selection, drag and drop, URL input, and file manager integration.
- Enter dates using the `dd-mm-yyyy` format.
- New special prices can be created without specifying dates.
- Access the original OpenCart product form through the **Full Product Form** button for additional fields or third-party extension settings.

## Category Manager

Manage your category structure more efficiently with a dedicated category management interface.

### Category Listing

- View the complete category path.
- Display category images, SEO URLs, product counts, and status.
- Sort all available columns.
- Search by category name or display name.
- Filter by status or parent category.

### Bulk Category Actions

- Enable or disable categories.
- Move categories to another parent.
- Show or hide categories in the top menu.

### Category Editor

Edit category information through a dedicated side drawer with four tabs:

- General
- Data
- SEO
- Design

**Custom Display Name**

Set a different display name for each language without changing the original category name. The display name is used on the storefront in menus, breadcrumbs, and category pages.

**Multiple Parent Categories**

Assign one category to multiple parent categories using the Multiple Parent Category functionality.

**Quick Add**

Create new categories directly from the Category Manager without navigating to the standard OpenCart form.

## Barcode Manager

Generate and manage product barcodes directly from your OpenCart administration panel.

- Generate Code128 barcodes using the product Model or SKU.
- Download generated barcodes as PNG images.
- Check Model and SKU uniqueness while the Barcode Manager is enabled.
- Optionally display product barcodes on storefront product pages using the default OpenCart theme and Barik themes.

## Extension Integrations

Quick Product Manager supports integration with selected Bariklabs extensions to provide a more complete management experience.

**Complete SEO**

Access additional SEO fields directly from the product and category editors:

- SEO Keyword
- SEO H1, H2, and H3
- Image Alt
- Image Title (for products)

**Multiple Parent Category**

Manage additional parent category assignments directly from the Category Manager.

**hpmm Media Manager**

Use the hpmm Media Manager image popover when the extension is installed. Otherwise, Quick Product Manager automatically uses its built-in image selector.

## Important Notes & Limitations

- **Third-party extension fields:** Data stored outside standard OpenCart product fields may be reset when saving a product through the drawer. For products using such extensions, use the Full Product Form instead.
- **Recurring tab:** The Recurring tab is not currently included in the side-drawer editor.
- **Required fields:** A product must have a name and meta title in every active language before it can be saved.
- **Category browser:** The default category tree browser is replaced by a searchable interface.
- **Language:** Only the English (en-gb) language file is included.

## Compatibility

- OpenCart 3.0.3.x
- Tested with OpenCart 3.0.3.9
- PHP 8.3
- MariaDB 10.11

**Additional requirement:** The Display Name feature uses OCMOD. Refresh modifications after installation.

## Installation Guide

1. Go to **Extensions > Installer** and upload the Quick Product Manager package.
2. Navigate to **Extensions > Modifications** and click Refresh.
3. Go to **Extensions > Extensions > Modules** and install Quick Product Manager.
4. Edit the module settings, set Status to Enabled, and save.
5. Optionally enable Category Manager through the module settings.

## Frequently Asked Questions

**Does Quick Product Manager modify my existing products automatically?**

No. Existing product data is only modified when you explicitly save an edit or perform an action, such as changing status, executing a bulk action, or using Quick Add.

**Can I still use the original OpenCart product form?**

Yes. Use the Full Product Form button to access the standard OpenCart product editor. You can also access the original product and category lists through the classic list options.

**What happens when I uninstall the extension?**

Your existing product and category data remain unchanged. The Display Name database table is retained.

**Does it support multiple languages and stores?**

Yes. Product descriptions, SEO URLs, and layouts support multiple languages and stores.

## Changelog

**Version 1.1.5.x**

- Customizable product list columns.
- Category images displayed in the product list.
- Bare-text attribute support.
- `dd-mm-yyyy` date input format.
- Case-insensitive search.
- Customizable drawer width.

**Version 1.1.4.0**

- Added built-in image popover.
- Added hpmm Media Manager popover integration.

**Version 1.1.2.x**

- Added Complete SEO integration for Keyword, SEO H1–H3, Image Alt, and Image Title.

**Version 1.1.0.9**

- Added Multiple Parent Category support in Category Manager.

**Version 1.1.0.0**

- Introduced Barcode Manager.
- Introduced Category Manager.
- Added Category Display Name functionality.

**Version 1.0.0.0**

- Initial release.

## Support

Need assistance or have suggestions?

Visit [Bariklabs.com](https://bariklabs.com) for product information, documentation, and support.
