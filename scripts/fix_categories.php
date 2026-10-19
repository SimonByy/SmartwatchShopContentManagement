<?php

/**
 * @file
 * Creates the missing Category subterms and assigns them to the imported
 * products.
 *
 * Usage: ddev drush php:script scripts/fix_categories.php
 * Safe to re-run.
 */

use Drupal\taxonomy\Entity\Term;

$etm = \Drupal::entityTypeManager();
$termStorage = $etm->getStorage('taxonomy_term');
$norm = fn($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
$vid = 'categorie';

// 1. Show what exists now.
echo "Huidige termen in '$vid':\n";
$existing = [];
foreach ($termStorage->loadTree($vid) as $t) {
  echo '  ' . str_repeat('-- ', $t->depth) . $t->name . "\n";
  $existing[$norm($t->name)] = (int) $t->tid;
}
if (!$existing) {
  echo "  (geen termen gevonden: controleer de machine name van de vocabulary)\n";
  return;
}

// 2. Parent > children structure.
$tree = [
  'Sports watches' => ['Running', 'Triathlon', 'Outdoor'],
  'Smartwatches' => ['Lifestyle', 'Health'],
  'Accessories' => ['Bands', 'Chargers', 'Sensors'],
];

$getOrCreate = function (string $name, int $parent = 0) use (&$existing, $norm, $vid) {
  if (isset($existing[$norm($name)])) {
    return $existing[$norm($name)];
  }
  $term = Term::create(['vid' => $vid, 'name' => $name, 'parent' => [$parent]]);
  $term->save();
  echo "  + term aangemaakt: $name\n";
  return $existing[$norm($name)] = (int) $term->id();
};

foreach ($tree as $parentName => $children) {
  $parentId = $getOrCreate($parentName);
  foreach ($children as $child) {
    $childId = $getOrCreate($child, $parentId);
    // Make sure an existing child hangs under the right parent.
    $term = Term::load($childId);
    if ((int) $term->parent->target_id !== $parentId) {
      $term->set('parent', [$parentId])->save();
      echo "  ~ parent gezet: $parentName > $child\n";
    }
  }
}

// 3. Assign categories to products.
$map = [
  'Garmin Forerunner 970' => 'Triathlon',
  'Garmin Forerunner 570' => 'Running',
  'Garmin Forerunner 265' => 'Running',
  'Garmin Fenix 9' => 'Outdoor',
  'Garmin Venu 4' => 'Lifestyle',
  'Garmin Instinct 3 AMOLED' => 'Outdoor',
  'Polar Vantage V3' => 'Triathlon',
  'Polar Grit X2 Pro' => 'Outdoor',
  'Polar Pacer Pro' => 'Running',
  'Amazfit Balance 3' => 'Running',
  'Amazfit T-Rex 3 Pro' => 'Outdoor',
  'Amazfit Active 3 Premium' => 'Running',
  'Google Pixel Watch 5' => 'Lifestyle',
  'Apple Watch Series 12' => 'Health',
  'Apple Watch Ultra 4' => 'Outdoor',
  'Apple Watch SE 3' => 'Lifestyle',
  'Garmin HRM 600' => 'Sensors',
  'Polar H10' => 'Sensors',
  'Garmin QuickFit 22 Silicone Band' => 'Bands',
  'Apple Sport Band (46 mm)' => 'Bands',
];

$efm = \Drupal::service('entity_field.manager');
$done = 0;
foreach ($map as $title => $category) {
  $products = $etm->getStorage('commerce_product')->loadByProperties(['title' => $title]);
  if (!$products) {
    echo "  ! product niet gevonden: $title\n";
    continue;
  }
  $product = reset($products);
  $field = NULL;
  foreach ($efm->getFieldDefinitions('commerce_product', $product->bundle()) as $name => $def) {
    if (str_starts_with($name, 'field_') && $norm($def->getLabel()) === 'category') {
      $field = $name;
    }
  }
  if (!$field) {
    echo "  ! geen Category-veld op product type {$product->bundle()}\n";
    continue;
  }
  $product->set($field, ['target_id' => $existing[$norm($category)]])->save();
  $done++;
}

echo "\nKlaar: categorie ingesteld voor $done producten.\n";
