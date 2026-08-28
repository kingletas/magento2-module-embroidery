# Commerce_Embroidery

Lets shoppers personalise garments: embroidered text, a font, a thread colour and a logo, on the left chest, the right chest, or both. Prices the choices into the cart, carries them onto the order, and renders them for whatever system fulfils the embroidery.

---

## Logo uploads

Uploads are the one place this module takes a file from a shopper, so the rules
are enforced in `LogoStorage` rather than left to each caller:

- the upload and delete endpoints are **POST with form-key validation**;
- a caller never supplies a path — `LogoStorage` accepts a bare file name,
  checks it against a strict generated-name pattern, and then verifies the
  resolved path is still inside the upload directory;
- uploads are stored under a **generated** name, so the stored name is never
  caller-controlled;
- there is an explicit size ceiling and image-content validation, not just an
  extension check;
- responses carry no paths and no exception text, and report "invalid name" and
  "not found" identically, so the endpoint cannot be used to probe the
  filesystem.

---

## Installation

```bash
composer require commerce/module-embroidery
bin/magento module:enable Commerce_Embroidery
bin/magento setup:upgrade
```

---

## Configuration

**Stores → Configuration → Catalog → Embroidery**

| Group | Setting | Notes |
| --- | --- | --- |
| General | Enable, CMS blocks for terms and upload guidance | |
| Charges | Per-line text prices, stock logo, custom logo, setup fee | Per store view |
| Notification | Recipients, sender, template | |

The custom-logo **setup fee** is charged once per cart line; the custom-logo **price** is charged per chest.

---

## Thread colours

Managed as data, with a CSV import at **System → Import → Embroidery Thread Colours**:

```csv
code,name,hex_code,pantone_code,sort_order,is_active
ceil-blue,Ceil Blue,#6095c1,PANTONE 645 C,40,1
```

`code` is a stable machine identifier, and it is what the storefront posts and the order stores. Identifying a colour by its **display name** instead means renaming one orphans every historical order that referenced it.

Colours were also listed **twice**: hardcoded in a bespoke `personalizer.xml` *and* held in the database with an admin grid and an import. The two drifted, and which list a shopper saw depended on which code path rendered the form.

---

## Fulfilment export

Field names belong to the receiving system, so they are configuration:

```xml
<type name="Commerce\Embroidery\Model\Export\FieldMapExportMapper">
    <arguments>
        <argument name="totalField" xsi:type="string">MonogramPrice</argument>
        <argument name="sideFieldTemplates" xsi:type="array">
            <item name="line_1" xsi:type="string">%sLineOne</item>
        </argument>
    </arguments>
</type>
```

Anything more involved implements `Api\OrderExportMapperInterface`.

---

## How pricing works

The surcharge is a **pure function of the cart line**, applied during totals
collection and read from the item's own stored option.

Both halves of that matter. Totals are recollected constantly — applying a
coupon, estimating shipping, a cron touching the quote — so pricing that reads
the shopper's choices from the request prices the line correctly once and then
reprices it *without* the surcharge on every recollection after: the customer
gets the embroidery free and fulfilment still receives the work order. And
because the charge is computed from the line rather than added to its current
price, recollection is idempotent instead of compounding.

`ChargeCalculator` owns the rules, in one place. Split across an observer, a
helper and a trait, the cart total and the exported `MonogramPrice` can disagree
about the same garment.

Selections are written directly as product options, which Magento persists and
converts onto the order itself. Anything that keys them off a hash of the item
while the item has no id, and moves them to the real key later, loses them for
any item added through a path where the second step does not run — the REST API,
or a save that throws in between.

## Order grid

Whether an order carries embroidery is a real column, populated at placement and
synced into `sales_order_grid`, so it can be filtered and sorted on. Computing
it in PHP per row means walking every item of every order on the page.

---

## Gotchas

- **`getList()` returns `ThreadColorSearchResultsInterface`, so the repository builds the result itself.** `Commerce_Foundation`'s `SearchResultBuilder` would otherwise create a generic `Magento\Framework\Api\SearchResults`, which does not implement that interface — a `TypeError` on every call. `ThreadColorSearchResults` exists for exactly that, and the preference points at it.
- **Stored logo names are generated, never taken from the client**, and anything not matching `^[a-f0-9]{32}\.(jpg|jpeg|png)$` is refused. A name written under a different scheme will not match, and cannot be deleted through this module.
- **A refused name and a missing file return the same answer.** That is deliberate, so the endpoint cannot be used to probe what exists on disk — but it does mean a genuine mismatch looks like a no-op. The refusal is logged.
- **The surcharge is applied during totals collection, so it recomputes on every collect.** It is written to be idempotent: the price is recalculated from the product each time rather than added to the current price.
- **The order grid flag is set at placement only.** Orders placed before this module was installed show `has_embroidery = 0` regardless of their items; backfilling is a separate data patch that is deliberately not included.
- **A custom-logo setup fee is charged once per cart line, not per chest.** Two chests with the same uploaded logo pay one fee.
- **`EmbroiderySelection` drops sides the shopper left blank**, so a selection built from a payload carrying an empty `right` has no right side at all rather than an empty one. Code that iterates `all()` sees only sides with something on them; code that expects a slot per side does not.
- **`OptionCodeInterface` is a constant holder, not a service.** Nothing implements it and nothing needs to; it exists so the three product-option codes have one definition. Asking whether an item carries embroidery is `SelectionReader::isEmbroidered()`, which is behaviour and lives with the other quote-item reading.

---

## Tests

```bash
M2_VENDOR=/path/to/magento/vendor php ../dev/run-tests.php -c ../dev/phpunit.xml
```

The suite runs against a real Magento installation without being installed into it. `dev/bootstrap.php` builds a PSR-4-only autoloader from that installation's composer map, which is also why it works where the host's own `vendor/autoload.php` is broken.

---

## Rebranding

```bash
php ../bin/rebrand Acme
```
