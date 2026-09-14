# Sendifico for Bagisto

[Sendifico](https://sendifico.com) shipping integration for
[Bagisto](https://bagisto.com) 2.x — home delivery across Ecuador with:

- **Territory matching** — Sendifico's coverage map is mirrored locally and
  checkout addresses are matched against it, so the method only offers itself
  where delivery actually exists
- **Flat or per-unit rate** — charge the configured rate once per order, or once
  for every unit of a shippable product in the cart
- **Free-shipping threshold** — configurable order total above which the rate
  drops to zero
- **Free shipping per product** — a "Free shipping" toggle in each product's
  Shipping group; those products add nothing to the charge, and an order
  holding only them ships free
- **Province surcharges** — a fixed amount added for chosen provinces (say,
  Galápagos), charged even when shipping is otherwise free
- **COD** — cash-on-delivery amounts calculated and passed to the carrier
- **Shipment lifecycle** — shipments are created from the admin order page,
  tracked, and synced back on a schedule
- **Full audit log** — every API exchange is recorded

## Requirements

- Bagisto `>= 2.4`
- PHP `>= 8.3`
- A Sendifico account and API key

## Installation

```bash
composer require tapstar/sendifico
```

The main service provider is auto-discovered. Because the package ships
Concord models (shipments, territories, logs), one manual step remains —
register the module in `config/concord.php`:

```php
'modules' => [
    // ...
    Tapstar\Sendifico\Providers\ModuleServiceProvider::class,
],
```

Then:

```bash
php artisan migrate
php artisan sendifico:territories   # first import of the coverage map
php artisan optimize:clear
```

## Configuration

Admin → Configure → Sales → **Carriers → Sendifico**: set the API key, the
default rate, whether it is charged per order or per unit shipped, and the
free-shipping threshold (0 disables it) and any province surcharges, one
`PROVINCE: amount` per line, then activate the method. Products
that ship free are marked with the **Free shipping** toggle on the product
itself.

## Scheduled tasks

The package registers its own schedule (requires the Laravel scheduler to be
running):

| Command | Cadence | Purpose |
|---|---|---|
| `sendifico:territories` | weekly | refresh the coverage map |
| `sendifico:sync` | every 4 hours | pull shipment status updates |

## License

Proprietary — © Tapstar. All rights reserved. The source is published for
reference; reuse requires permission.

---

Built by [Tapstar](https://tapstar.ec) — NFC devices for businesses in
Ecuador.
