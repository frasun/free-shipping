# Free Shipping by Location — WooCommerce Plugin

Displays location-aware free shipping information and handles free shipping logic
across cart, checkout, and shipping method labels. Threshold is read directly
from WooCommerce shipping zone settings — no separate configuration needed.

## Features

- Reads free shipping threshold from WooCommerce shipping zones per customer country
- Hides the free shipping method and zeroes out cost on eligible paid methods instead
- Per-method opt-out: adds "Exclude from free shipping" checkbox to each shipping method's settings
- Displays cart/checkout notice with remaining amount needed for free shipping, updated via AJAX fragments
- Appends free shipping label to shipping method when cost is zero
- Public function `chocante_free_shipping_display_info()` to render threshold info anywhere in the theme
- Shipping zone lookups cached via `wp_cache` per country and currency
- Compatible with WooCommerce Multilingual (WCML) and Curcy (free & premium) for multi-currency threshold conversion
