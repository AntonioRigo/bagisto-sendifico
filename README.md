# Sendifico for Bagisto

[Sendifico](https://sendifico.com) shipping integration for
[Bagisto](https://bagisto.com) 2.x — home delivery across Ecuador with:

- **Territory matching** — Sendifico's coverage map is mirrored locally and
  checkout addresses are matched against it, so the method only offers itself
  where delivery actually exists
- **Free-shipping threshold** — configurable order total above which the rate
  drops to zero
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
default rate, and the free-shipping threshold, then activate the method.

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
