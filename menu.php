<?php
/**
 * menu.php
 * --------
 * Public, read-only menu page - what a customer sees after scanning a
 * QR code on their table. Pulls live from the same Aiven database the
 * desktop app and reservation page use, so any change made on the
 * Menu screen (add/edit/delete a dish) shows up here automatically.
 *
 * Uses the same environment variables as index.php (DB_HOST, DB_PORT,
 * DB_NAME, DB_USER, DB_PASS) - no separate configuration needed.
 *
 * PHOTOS: this looks for image files in images/food/ and
 * images/drinks/, using the same relative paths already stored in the
 * database (the same ones the desktop app's Menu screen uses). If you
 * want real photos to show here too, upload your images/ folder
 * (the same one from your desktop project) into this GitHub repository,
 * alongside menu.php - Render will pick them up automatically on the
 * next deploy. Without them, dishes just show a simple icon instead,
 * the same graceful fallback the desktop app uses.
 */

$dbHost = getenv('DB_HOST') ?: 'mysql-335f3a45-isaacoseiameyaw992-3a9a.c.aivencloud.com';
$dbPort = getenv('DB_PORT') ?: '27008';
$dbName = getenv('DB_NAME') ?: 'restaurant_db';
$dbUser = getenv('DB_USER') ?: 'avnadmin';
$dbPass = getenv('DB_PASS') ?: '';

$foods = [];
$drinksByCategory = ['SOFT_DRINK' => [], 'WATER' => [], 'WINE' => []];
$categoryLabels = ['SOFT_DRINK' => 'Soft Drinks', 'WATER' => 'Water', 'WINE' => 'Wine'];
$loadError = null;

if ($dbPass === '') {
    $loadError = "The menu isn't available right now. Please ask staff for a printed menu.";
} else {
    try {
        $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        ]);

        $foods = $pdo->query("SELECT name, description, price, image_path FROM food ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);

        $drinks = $pdo->query("SELECT name, category, price, image_path FROM drinks ORDER BY id")
            ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($drinks as $drink) {
            $cat = $drink['category'];
            if (!isset($drinksByCategory[$cat])) {
                $drinksByCategory[$cat] = [];
            }
            $drinksByCategory[$cat][] = $drink;
        }
    } catch (PDOException $e) {
        $loadError = "The menu isn't available right now. Please ask staff for a printed menu.";
    }
}

/** Shows a real photo if the file exists in this repo, otherwise a simple icon placeholder. */
function renderImage(?string $imagePath, string $fallbackIcon): void {
    if ($imagePath && file_exists($imagePath)) {
        echo '<img class="dish-photo" src="' . htmlspecialchars($imagePath) . '" alt="">';
    } else {
        echo '<div class="dish-photo dish-photo-fallback">' . $fallbackIcon . '</div>';
    }
}

function foodIcon(string $name): string {
    $lower = strtolower($name);
    if (str_contains($lower, 'rice')) return '&#127770;';
    if (str_contains($lower, 'tilapia') || str_contains($lower, 'fish')) return '&#128031;';
    if (str_contains($lower, 'fufu') || str_contains($lower, 'banku')) return '&#127858;';
    if (str_contains($lower, 'chicken')) return '&#127831;';
    return '&#127869;&#65039;';
}

function drinkIcon(string $category): string {
    return match ($category) {
        'WATER' => '&#128167;',
        'WINE' => '&#127863;',
        default => '&#129396;',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu - Chop House</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background:#F4F6F5; margin:0; padding:0; color:#1E1E1E; }
  .wrap { max-width: 560px; margin: 0 auto; padding: 24px 16px 48px; }
  h1 { color:#154D37; margin-bottom:4px; }
  p.sub { color:#6B7280; margin-top:0; }
  h2.section { color:#154D37; font-size:19px; margin: 32px 0 12px; }
  .card { background:#fff; border-radius:14px; padding:14px; margin-bottom:12px; display:flex; gap:14px; align-items:center; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
  .dish-photo { width:64px; height:64px; border-radius:10px; object-fit:cover; flex-shrink:0; }
  .dish-photo-fallback { display:flex; align-items:center; justify-content:center; font-size:28px; background:#EAF3EF; }
  .dish-info { flex:1; }
  .dish-name { font-weight:600; margin:0 0 2px; }
  .dish-desc { color:#6B7280; font-size:13px; margin:0 0 6px; }
  .dish-price { display:inline-block; background:#1F6F50; color:#fff; font-size:13px; font-weight:600; padding:3px 10px; border-radius:10px; }
  .error-box { background:#FDECEC; color:#C0392B; padding:16px; border-radius:10px; }
  .reserve-btn { display:inline-block; margin-top:12px; background:#1F6F50; color:#fff; text-decoration:none;
                 padding:10px 22px; border-radius:12px; font-weight:600; font-size:14px; }
  .reserve-btn:hover { background:#154D37; }
</style>
</head>
<body>
<div class="wrap">
  <h1>&#127869;&#65039; Chop House</h1>
  <p class="sub">Our menu, priced in Ghana Cedis (GH&#8373;)</p>
  <a href="index.php" class="reserve-btn">Reserve a Table</a>

  <?php if ($loadError): ?>
    <div class="error-box"><?= htmlspecialchars($loadError) ?></div>
  <?php else: ?>

    <h2 class="section">Food</h2>
    <?php foreach ($foods as $food): ?>
      <div class="card">
        <?php renderImage($food['image_path'], foodIcon($food['name'])); ?>
        <div class="dish-info">
          <p class="dish-name"><?= htmlspecialchars($food['name']) ?></p>
          <p class="dish-desc"><?= htmlspecialchars($food['description']) ?></p>
          <span class="dish-price">GH&#8373; <?= number_format((float)$food['price'], 2) ?></span>
        </div>
      </div>
    <?php endforeach; ?>

    <?php foreach ($drinksByCategory as $cat => $items): ?>
      <?php if (empty($items)) continue; ?>
      <h2 class="section"><?= htmlspecialchars($categoryLabels[$cat] ?? $cat) ?></h2>
      <?php foreach ($items as $drink): ?>
        <div class="card">
          <?php renderImage($drink['image_path'], drinkIcon($drink['category'])); ?>
          <div class="dish-info">
            <p class="dish-name"><?= htmlspecialchars($drink['name']) ?></p>
            <span class="dish-price">GH&#8373; <?= number_format((float)$drink['price'], 2) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

  <?php endif; ?>
</div>
</body>
</html>
