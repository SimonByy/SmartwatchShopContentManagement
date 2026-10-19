<?php

/**
 * @file
 * Imports the SmartwatchShop demo catalogue (15 smartwatches + 4 accessories).
 *
 * Usage (from the project root):
 *   ddev drush php:script scripts/import_products.php
 *
 * Safe to re-run: products that already exist (same title) and existing SKUs
 * are skipped. Missing attribute values (e.g. "48 mm") are created
 * automatically. Fields, terms and list values are matched by label, so the
 * script adapts to the machine names chosen in the UI.
 *
 * Spec source: manufacturer specifications as collected in the5krunner.com
 * spec database (October 2026). Prices are shop prices in EUR based on the
 * official launch RRP; prices marked "estimate" are derived from UK/US
 * pricing because no official EUR price was published.
 */

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;

$etm = \Drupal::entityTypeManager();
$efm = \Drupal::service('entity_field.manager');
$store = \Drupal::service('commerce_store.default_store_resolver')->resolve();
if (!$store) {
  echo "Geen default store gevonden. Maak eerst een store aan.\n";
  return;
}

// Normalise labels so "Display Type", "display_type" and "Display type"
// all match.
$norm = fn($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));

// Vocabulary machine names (created in Dutch, labels translated later).
$vocab = [
  'brand' => 'merk',
  'category' => 'categorie',
  'features' => 'features',
  'sports' => 'sporten',
  'compatibility' => 'compatibiliteit',
];

// --- Helpers ---------------------------------------------------------------

$vocabCache = [];
$term = function (string $vid, string $name) use ($etm, $norm, &$vocabCache) {
  if (!isset($vocabCache[$vid])) {
    $vocabCache[$vid] = [];
    $terms = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
    foreach ($terms as $t) {
      $vocabCache[$vid][$norm($t->getName())] = (int) $t->id();
    }
  }
  $id = $vocabCache[$vid][$norm($name)] ?? NULL;
  if ($id === NULL) {
    echo "  ! term niet gevonden: $vid / $name\n";
  }
  return $id;
};

$fieldMap = function (string $entityType, string $bundle) use ($efm, $norm) {
  $map = [];
  foreach ($efm->getFieldDefinitions($entityType, $bundle) as $name => $def) {
    if (str_starts_with($name, 'field_')) {
      $map[$norm($def->getLabel())] = $def;
    }
  }
  return $map;
};

$listKey = function ($def, string $label) use ($norm) {
  $allowed = $def->getFieldStorageDefinition()->getSetting('allowed_values') ?? [];
  foreach ($allowed as $key => $value) {
    if ($norm($value) === $norm($label) || $norm($key) === $norm($label)) {
      return $key;
    }
  }
  echo "  ! lijstwaarde niet gevonden: $label\n";
  return NULL;
};

$attrValue = function (string $attribute, string $name) use ($etm, $norm) {
  $storage = $etm->getStorage('commerce_product_attribute_value');
  foreach ($storage->loadByProperties(['attribute' => $attribute]) as $value) {
    if ($norm($value->getName()) === $norm($name)) {
      return (int) $value->id();
    }
  }
  $value = ProductAttributeValue::create(['attribute' => $attribute, 'name' => $name]);
  $value->save();
  echo "  + attribuutwaarde aangemaakt: $attribute / $name\n";
  return (int) $value->id();
};

$resolveType = function (string $label) use ($norm) {
  foreach (ProductType::loadMultiple() as $type) {
    if ($norm($type->id()) === $norm($label) || $norm($type->label()) === $norm($label)) {
      $variationType = method_exists($type, 'getVariationTypeIds')
        ? $type->getVariationTypeIds()[0]
        : $type->getVariationTypeId();
      return [$type->id(), $variationType];
    }
  }
  return [NULL, NULL];
};

$productId = function (string $title) use ($etm) {
  $found = $etm->getStorage('commerce_product')->loadByProperties(['title' => $title]);
  return $found ? (int) reset($found)->id() : NULL;
};

// Shorthand for a smartwatch variation.
$w = fn($sku, $price, $size, $color, $material, $conn = 'GPS') => [
  'sku' => $sku,
  'price' => $price,
  'attrs' => [
    'case_size' => $size,
    'color' => $color,
    'material' => $material,
    'connectivity' => $conn,
  ],
];
// Shorthand for an accessory variation.
$a = fn($sku, $price, $color, $width = NULL, $length = NULL) => [
  'sku' => $sku,
  'price' => $price,
  'attrs' => array_filter([
    'color' => $color,
    'band_width' => $width,
    'band_length' => $length,
  ]),
];

// --- Catalogue -------------------------------------------------------------

$products = [

  // ===== Garmin =====
  [
    'type' => 'Smartwatch',
    'title' => 'Garmin Forerunner 970',
    'series' => 'Forerunner',
    'category' => 'Triathlon',
    'features' => ['Multi-band GPS', 'Offline maps', 'ECG', 'SpO2', 'NFC payments', 'Music storage', 'Flashlight'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Triathlon'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 15, 'batt_gps' => 26, 'weight' => 56, 'year' => 2025,
    'body' => '<p>Garmin\'s flagship running and triathlon watch with a bright AMOLED display, sapphire lens and titanium bezel. A built-in LED flashlight, the ECG app, multi-band GPS and full-colour offline maps make it ready for any race or training block.</p>',
    'variations' => [
      $w('GAR-FR970-47-GRY', '749.99', '47 mm', 'Grey', 'Fibre-reinforced polymer'),
      $w('GAR-FR970-47-WHT', '749.99', '47 mm', 'White', 'Fibre-reinforced polymer'),
      $w('GAR-FR970-47-GLD', '749.99', '47 mm', 'Gold', 'Fibre-reinforced polymer'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Garmin Forerunner 570',
    'series' => 'Forerunner',
    'category' => 'Running',
    'features' => ['Multi-band GPS', 'SpO2', 'NFC payments', 'Music storage'],
    'sports' => ['Running', 'Cycling', 'Swimming', 'Triathlon'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 11, 'batt_gps' => 18, 'weight' => 50, 'year' => 2025,
    'body' => '<p>The lighter sibling of the Forerunner 970, with multi-band GPS, an AMOLED touchscreen, Garmin Pay and a built-in speaker and microphone. Available in 42 mm and 47 mm. Battery and weight figures are for the 47 mm model.</p>',
    'variations' => [
      $w('GAR-FR570-42-BLK', '549.99', '42 mm', 'Black', 'Fibre-reinforced polymer'),
      $w('GAR-FR570-42-WHT', '549.99', '42 mm', 'White', 'Fibre-reinforced polymer'),
      $w('GAR-FR570-47-BLK', '549.99', '47 mm', 'Black', 'Fibre-reinforced polymer'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Garmin Fenix 9',
    'series' => 'Fenix',
    'category' => 'Outdoor',
    'features' => ['Multi-band GPS', 'Offline maps', 'ECG', 'SpO2', 'NFC payments', 'Music storage', 'Flashlight'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Triathlon', 'Golf', 'Skiing'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '10 ATM + diving',
    'batt_sw' => 16, 'batt_gps' => 30, 'weight' => 75, 'year' => 2026,
    'body' => '<p>Garmin\'s premium multisport adventure watch, launched in August 2026. A 2,000-nit AMOLED display, titanium bezel, preloaded TopoActive maps, LED flashlight and a dive computer rated to 40 m. Battery and weight figures are for the 47 mm model.</p>',
    'variations' => [
      $w('GAR-FNX9-43-GRY', '999.99', '43 mm', 'Grey', 'Titanium'),
      $w('GAR-FNX9-47-GRY', '999.99', '47 mm', 'Grey', 'Titanium'),
      $w('GAR-FNX9-47-BLK', '999.99', '47 mm', 'Black', 'Titanium'),
      $w('GAR-FNX9-51-BLK', '1099.99', '51 mm', 'Black', 'Titanium'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Garmin Venu 4',
    'series' => 'Venu',
    'category' => 'Lifestyle',
    'features' => ['Multi-band GPS', 'ECG', 'SpO2', 'NFC payments', 'Music storage', 'Flashlight'],
    'sports' => ['Running', 'Cycling', 'Swimming', 'Golf'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 12, 'batt_gps' => 20, 'weight' => 56, 'year' => 2025,
    'body' => '<p>A lifestyle smartwatch with serious training features: multi-band GPS, the ECG app, an LED flashlight and a stainless steel bezel. Up to 12 days of battery life. Battery and weight (with band) figures are for the 45 mm model.</p>',
    'variations' => [
      $w('GAR-VENU4-41-GLD', '599.99', '41 mm', 'Gold', 'Stainless steel'),
      $w('GAR-VENU4-45-SLV', '599.99', '45 mm', 'Silver', 'Stainless steel'),
      $w('GAR-VENU4-45-GRY', '599.99', '45 mm', 'Grey', 'Stainless steel'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Garmin Instinct 3 AMOLED',
    'series' => 'Instinct',
    'category' => 'Outdoor',
    'features' => ['Multi-band GPS', 'SpO2', 'NFC payments', 'Flashlight'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Skiing'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '10 ATM',
    'batt_sw' => 18, 'batt_gps' => 32, 'weight' => 53, 'year' => 2025,
    'body' => '<p>A rugged five-button outdoor watch with the first colour AMOLED display in the Instinct line, multi-band GPS, Garmin Pay and a built-in flashlight. Water rated to 100 m and tested to MIL-STD-810. Battery and weight figures are for the 45 mm model.</p>',
    'variations' => [
      // EUR price estimate (USD 449.99 / 499.99).
      $w('GAR-INST3A-45-BLK', '449.99', '45 mm', 'Black', 'Fibre-reinforced polymer'),
      $w('GAR-INST3A-50-BLK', '499.99', '50 mm', 'Black', 'Fibre-reinforced polymer'),
    ],
  ],

  // ===== Polar =====
  [
    'type' => 'Smartwatch',
    'title' => 'Polar Vantage V3',
    'series' => 'Vantage',
    'category' => 'Triathlon',
    'features' => ['Multi-band GPS', 'Offline maps', 'ECG', 'SpO2'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Triathlon'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 10, 'batt_gps' => 43, 'weight' => 57, 'year' => 2023,
    'body' => '<p>Polar\'s flagship multisport watch with an AMOLED touchscreen, dual-frequency GPS, offline TOPO maps and the Elixir sensor suite with ECG and SpO2. Up to 43 hours of GPS training on a single charge.</p>',
    'variations' => [
      $w('POL-VV3-47-BLK', '599.90', '47 mm', 'Black', 'Aluminium'),
      $w('POL-VV3-47-BLU', '599.90', '47 mm', 'Blue', 'Aluminium'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Polar Grit X2 Pro',
    'series' => 'Grit X',
    'category' => 'Outdoor',
    'features' => ['Multi-band GPS', 'Offline maps', 'ECG', 'SpO2'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Skiing'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '10 ATM',
    'batt_sw' => 10, 'batt_gps' => 43, 'weight' => 79, 'year' => 2024,
    'body' => '<p>Polar\'s rugged outdoor flagship with sapphire glass, MIL-STD-810H durability and preloaded offline maps. The same software as the Vantage V3 in a tougher stainless steel case, or a lighter titanium case in the Titan edition (64 g).</p>',
    'variations' => [
      $w('POL-GX2P-49-BLK', '750.00', '49 mm', 'Black', 'Stainless steel'),
      $w('POL-GX2P-49-GRY', '750.00', '49 mm', 'Grey', 'Stainless steel'),
      $w('POL-GX2P-49-TIT', '870.00', '49 mm', 'Black', 'Titanium'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Polar Pacer Pro',
    'series' => 'Pacer',
    'category' => 'Running',
    'features' => [],
    'sports' => ['Running', 'Cycling', 'Swimming'],
    'compat' => ['iOS', 'Android'],
    'display' => 'MIP', 'water' => '5 ATM',
    'batt_sw' => 6, 'batt_gps' => 30, 'weight' => 41, 'year' => 2022,
    'body' => '<p>A lightweight running watch (41 g) with barometer, compass, route guidance and Strava Live Segments. Great value for runners who want reliable training data without paying for extras they do not need.</p>',
    'variations' => [
      $w('POL-PACPRO-45-GRY', '299.90', '45 mm', 'Grey', 'Aluminium'),
      $w('POL-PACPRO-45-WHT', '299.90', '45 mm', 'White', 'Aluminium'),
      $w('POL-PACPRO-45-BLU', '299.90', '45 mm', 'Blue', 'Aluminium'),
    ],
  ],

  // ===== Amazfit =====
  [
    'type' => 'Smartwatch',
    'title' => 'Amazfit Balance 3',
    'series' => 'Balance',
    'category' => 'Running',
    'features' => ['Multi-band GPS', 'Offline maps', 'SpO2', 'NFC payments', 'Music storage', 'Flashlight'],
    'sports' => ['Running', 'Cycling', 'Swimming', 'Golf', 'Skiing'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '10 ATM + diving',
    'batt_sw' => 21, 'batt_gps' => 41, 'weight' => NULL, 'year' => 2026,
    'body' => '<p>A hybrid training watch built for HYROX, running and the gym, with a 3,000-nit sapphire AMOLED display, dual-band GPS, offline maps and Zepp Pay. Certified for freediving and recreational scuba to 45 m.</p>',
    'variations' => [
      // EUR price estimate (GBP 369.90 / 449.90).
      $w('AMZ-BAL3-51-SLV', '369.90', '51 mm', 'Silver', 'Stainless steel'),
      $w('AMZ-BAL3-51-BLK', '449.90', '51 mm', 'Black', 'Titanium'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Amazfit T-Rex 3 Pro',
    'series' => 'T-Rex',
    'category' => 'Outdoor',
    'features' => ['Multi-band GPS', 'Offline maps', 'SpO2', 'NFC payments', 'Music storage', 'Flashlight'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Skiing'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '10 ATM + diving',
    'batt_sw' => 25, 'batt_gps' => 38, 'weight' => 52, 'year' => 2025,
    'body' => '<p>A rugged outdoor watch with a Grade 5 titanium bezel, sapphire glass and a 3,000-nit AMOLED display. Offline maps with auto-rerouting, a dual-colour LED flashlight and up to 25 days of battery life. Battery and weight figures are for the 48 mm model.</p>',
    'variations' => [
      // EUR price estimate (GBP 399.90 / USD 399).
      $w('AMZ-TREX3P-44-BLK', '399.90', '44 mm', 'Black', 'Titanium'),
      $w('AMZ-TREX3P-48-BLK', '399.90', '48 mm', 'Black', 'Titanium'),
      $w('AMZ-TREX3P-48-GLD', '399.90', '48 mm', 'Gold', 'Titanium'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Amazfit Active 3 Premium',
    'series' => 'Active',
    'category' => 'Running',
    'features' => ['Offline maps', 'SpO2', 'NFC payments'],
    'sports' => ['Running', 'Cycling', 'Swimming'],
    'compat' => ['iOS', 'Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 12, 'batt_gps' => 24, 'weight' => 38, 'year' => 2026,
    'body' => '<p>An affordable running and lifestyle smartwatch with a stainless steel frame and a 3,000-nit sapphire AMOLED display. Offline maps with turn-by-turn navigation, Zepp Pay and up to 12 days of battery life.</p>',
    'variations' => [
      $w('AMZ-ACT3P-45-SLV', '169.90', '45 mm', 'Silver', 'Stainless steel'),
      $w('AMZ-ACT3P-45-BLU', '169.90', '45 mm', 'Blue', 'Stainless steel'),
      $w('AMZ-ACT3P-45-WHT', '169.90', '45 mm', 'White', 'Stainless steel'),
    ],
  ],

  // ===== Google =====
  [
    'type' => 'Smartwatch',
    'title' => 'Google Pixel Watch 5',
    'series' => 'Pixel Watch',
    'category' => 'Lifestyle',
    'features' => ['Multi-band GPS', 'ECG', 'SpO2', 'NFC payments', 'LTE', 'Music storage'],
    'sports' => ['Running', 'Cycling', 'Swimming'],
    'compat' => ['Android'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 1, 'batt_gps' => NULL, 'weight' => 37, 'year' => 2026,
    'body' => '<p>Google\'s Wear OS 7 smartwatch with Gemini, dual-frequency GPS with cloud post-processing, ECG and the new Health Guardian features. Android only. Up to 40 hours with the always-on display (45 mm, weight without band).</p>',
    'variations' => [
      $w('GOO-PW5-41-BLK', '419.00', '41 mm', 'Black', 'Aluminium'),
      $w('GOO-PW5-41-SLV', '419.00', '41 mm', 'Silver', 'Aluminium'),
      // EUR price estimates (GBP 399 / 499).
      $w('GOO-PW5-45-BLK', '449.00', '45 mm', 'Black', 'Aluminium'),
      $w('GOO-PW5-45-BLK-LTE', '549.00', '45 mm', 'Black', 'Aluminium', 'GPS + Cellular'),
    ],
  ],

  // ===== Apple =====
  [
    'type' => 'Smartwatch',
    'title' => 'Apple Watch Series 12',
    'series' => 'Watch Series',
    'category' => 'Health',
    'features' => ['ECG', 'SpO2', 'NFC payments', 'LTE', 'Music storage'],
    'sports' => ['Running', 'Cycling', 'Swimming'],
    'compat' => ['iOS'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 1, 'batt_gps' => 10, 'weight' => 39, 'year' => 2026,
    'body' => '<p>The new mainstream Apple Watch (2026) with the S11 chip, a redesigned Health Sensing System, ECG and the new Readiness app. Up to 24 hours of battery life and 10 hours of outdoor workout. Requires an iPhone. Weight: 46 mm aluminium.</p>',
    'variations' => [
      $w('APL-S12-42-BLK', '449.00', '42 mm', 'Black', 'Aluminium'),
      $w('APL-S12-42-GLD', '449.00', '42 mm', 'Gold', 'Aluminium'),
      $w('APL-S12-46-BLK', '499.00', '46 mm', 'Black', 'Aluminium'),
      $w('APL-S12-46-BLK-LTE', '619.00', '46 mm', 'Black', 'Aluminium', 'GPS + Cellular'),
      $w('APL-S12-46-SLV-TI', '849.00', '46 mm', 'Silver', 'Titanium', 'GPS + Cellular'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Apple Watch Ultra 4',
    'series' => 'Watch Ultra',
    'category' => 'Outdoor',
    'features' => ['Multi-band GPS', 'ECG', 'SpO2', 'NFC payments', 'LTE', 'Music storage'],
    'sports' => ['Running', 'Trail running', 'Cycling', 'Swimming', 'Triathlon'],
    'compat' => ['iOS'],
    'display' => 'AMOLED', 'water' => '10 ATM + diving',
    'batt_sw' => 2, 'batt_gps' => 18, 'weight' => 63, 'year' => 2026,
    'body' => '<p>Apple\'s rugged flagship with a 49 mm titanium case, dual-frequency GPS, satellite messaging and dive certification to 40 m. Up to 50 hours of battery life and 18 hours of outdoor workout. Requires an iPhone.</p>',
    'variations' => [
      $w('APL-ULT4-49-SLV', '899.00', '49 mm', 'Silver', 'Titanium', 'GPS + Cellular'),
      $w('APL-ULT4-49-BLK', '899.00', '49 mm', 'Black', 'Titanium', 'GPS + Cellular'),
    ],
  ],
  [
    'type' => 'Smartwatch',
    'title' => 'Apple Watch SE 3',
    'series' => 'Watch SE',
    'category' => 'Lifestyle',
    'features' => ['NFC payments', 'LTE', 'Music storage'],
    'sports' => ['Running', 'Cycling', 'Swimming'],
    'compat' => ['iOS'],
    'display' => 'AMOLED', 'water' => '5 ATM',
    'batt_sw' => 1, 'batt_gps' => 6, 'weight' => 33, 'year' => 2025,
    'body' => '<p>Apple\'s most affordable watch, now with an always-on display, the S10 chip, Sleep Score and fast charging. Up to 18 hours of battery life. No ECG or blood oxygen sensor. Requires an iPhone. Weight: 44 mm.</p>',
    'variations' => [
      $w('APL-SE3-40-BLK', '269.00', '40 mm', 'Black', 'Aluminium'),
      $w('APL-SE3-40-WHT', '269.00', '40 mm', 'White', 'Aluminium'),
      // EUR price estimate.
      $w('APL-SE3-44-BLK', '299.00', '44 mm', 'Black', 'Aluminium'),
    ],
  ],

  // ===== Accessories =====
  [
    'type' => 'Accessory',
    'title' => 'Garmin HRM 600',
    'series' => 'Garmin',
    'category' => 'Sensors',
    'compatible' => ['Garmin Forerunner 970', 'Garmin Forerunner 570', 'Garmin Forerunner 265', 'Garmin Fenix 9', 'Garmin Venu 4', 'Garmin Instinct 3 AMOLED'],
    'body' => '<p>Garmin\'s premium chest strap with ANT+ and Bluetooth, onboard data storage and advanced running dynamics. Required for Running Economy and Step Speed Loss on the Forerunner 970.</p>',
    'variations' => [
      $a('GAR-HRM600-SM', '169.99', 'Black', NULL, 'S/M'),
      $a('GAR-HRM600-ML', '169.99', 'Black', NULL, 'M/L'),
    ],
  ],
  [
    'type' => 'Accessory',
    'title' => 'Polar H10',
    'series' => 'Polar',
    'category' => 'Sensors',
    'compatible' => ['Polar Vantage V3', 'Polar Grit X2 Pro', 'Polar Pacer Pro'],
    'body' => '<p>Polar\'s reference chest strap with Bluetooth and ANT+, internal memory for a training session and excellent accuracy for heart rate variability measurements.</p>',
    'variations' => [
      // Shop price, not an official RRP.
      $a('POL-H10-SM', '89.90', 'Black', NULL, 'S/M'),
      $a('POL-H10-ML', '89.90', 'Black', NULL, 'M/L'),
    ],
  ],
  [
    'type' => 'Accessory',
    'title' => 'Garmin QuickFit 22 Silicone Band',
    'series' => 'Garmin',
    'category' => 'Bands',
    'compatible' => ['Garmin Fenix 9', 'Garmin Instinct 3 AMOLED'],
    'body' => '<p>A silicone replacement band with Garmin\'s QuickFit system: swap bands in seconds without tools. Fits 22 mm QuickFit watches such as the Fenix 9 (47 mm) and Instinct 3 (45 mm).</p>',
    'variations' => [
      // Shop price, estimate.
      $a('GAR-QF22-BLK', '49.99', 'Black', '22 mm'),
      $a('GAR-QF22-GRY', '49.99', 'Grey', '22 mm'),
    ],
  ],
  [
    'type' => 'Accessory',
    'title' => 'Apple Sport Band (46 mm)',
    'series' => 'Apple',
    'category' => 'Bands',
    'compatible' => ['Apple Watch Series 12', 'Apple Watch SE 3', 'Apple Watch Ultra 4'],
    'body' => '<p>A soft fluoroelastomer sport band with a pin-and-tuck closure. Fits Apple Watch cases of 44, 45, 46 and 49 mm.</p>',
    'variations' => [
      // Shop price, estimate.
      $a('APL-SB46-BLK-SM', '49.00', 'Black', NULL, 'S/M'),
      $a('APL-SB46-BLK-ML', '49.00', 'Black', NULL, 'M/L'),
      $a('APL-SB46-WHT-ML', '49.00', 'White', NULL, 'M/L'),
    ],
  ],
];

// --- Import ----------------------------------------------------------------

$productStorage = $etm->getStorage('commerce_product');
$variationStorage = $etm->getStorage('commerce_product_variation');
$count = 0;

foreach ($products as $p) {
  [$productType, $variationType] = $resolveType($p['type']);
  if (!$productType) {
    echo "! product type '{$p['type']}' niet gevonden, overgeslagen: {$p['title']}\n";
    continue;
  }
  if ($productStorage->loadByProperties(['title' => $p['title']])) {
    echo "= bestaat al: {$p['title']}\n";
    continue;
  }

  $fields = $fieldMap('commerce_product', $productType);
  $product = Product::create([
    'type' => $productType,
    'title' => $p['title'],
    'stores' => [$store->id()],
    'status' => 1,
    'body' => ['value' => $p['body'], 'format' => 'basic_html'],
  ]);

  // Taxonomy references.
  $refs = [
    'brand' => [$p['series']],
    'category' => [$p['category']],
    'features' => $p['features'] ?? [],
    'sports' => $p['sports'] ?? [],
    'compatibility' => $p['compat'] ?? [],
  ];
  foreach ($refs as $key => $names) {
    if (!isset($fields[$key]) || !$names) {
      continue;
    }
    $ids = array_values(array_filter(array_map(fn($n) => $term($vocab[$key], $n), $names)));
    $product->set($fields[$key]->getName(), array_map(fn($id) => ['target_id' => $id], $ids));
  }

  // List fields.
  foreach (['displaytype' => 'display', 'waterresistance' => 'water'] as $fieldKey => $dataKey) {
    if (isset($fields[$fieldKey], $p[$dataKey])) {
      $key = $listKey($fields[$fieldKey], $p[$dataKey]);
      if ($key !== NULL) {
        $product->set($fields[$fieldKey]->getName(), $key);
      }
    }
  }

  // Number fields (NULL = unknown, left empty).
  $numbers = [
    'batterylifesmartwatch' => 'batt_sw',
    'batterylifegps' => 'batt_gps',
    'weight' => 'weight',
    'releaseyear' => 'year',
  ];
  foreach ($numbers as $fieldKey => $dataKey) {
    if (isset($fields[$fieldKey], $p[$dataKey])) {
      $product->set($fields[$fieldKey]->getName(), $p[$dataKey]);
    }
  }

  // Accessory -> compatible smartwatches.
  if (!empty($p['compatible']) && isset($fields['compatiblewith'])) {
    $ids = array_values(array_filter(array_map($productId, $p['compatible'])));
    $product->set($fields['compatiblewith']->getName(), array_map(fn($id) => ['target_id' => $id], $ids));
  }

  $product->save();

  // Variations (created after the product so titles can be generated).
  $variationFields = array_keys($efm->getFieldDefinitions('commerce_product_variation', $variationType));
  $added = 0;
  foreach ($p['variations'] as $v) {
    if ($variationStorage->loadByProperties(['sku' => $v['sku']])) {
      echo "  = SKU bestaat al: {$v['sku']}\n";
      continue;
    }
    $values = [
      'type' => $variationType,
      'sku' => $v['sku'],
      'price' => new Price($v['price'], 'EUR'),
      'status' => 1,
      'product_id' => $product->id(),
    ];
    foreach ($v['attrs'] as $attribute => $name) {
      if (in_array("attribute_$attribute", $variationFields, TRUE)) {
        $values["attribute_$attribute"] = $attrValue($attribute, $name);
      }
    }
    $variation = ProductVariation::create($values);
    $variation->save();
    $product->addVariation($variation);
    $added++;
  }
  $product->save();
  $count++;
  echo "+ {$p['title']} ($added variaties)\n";
}

echo "\nKlaar: $count producten aangemaakt.\n";
