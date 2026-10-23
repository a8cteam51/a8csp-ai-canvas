<?php declare( strict_types=1 ); // Silence is golden.

// Namespace-less classes that are part of a public contract belong here — the shape WooCommerce
// itself uses for `WC_Order` and friends. Composer classmaps `models/` via composer.json's
// `autoload.classmap`, so these classes load without a PSR-4 path; everything namespaced stays
// under `src/` in its feature folder.
