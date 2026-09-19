<?php
/**
 * index.php
 * ---------
 * Public online reservation form for Chop House. Connects directly to
 * the same Aiven MySQL database used by the desktop app, so a booking
 * made here shows up immediately in the app's Reservations screen.
 *
 * CONFIGURATION: database settings come from environment variables
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS), set in your hosting
 * platform's dashboard (e.g. Koyeb's "Environment variables" section)
 * rather than hardcoded here. This means the real password never has
 * to be committed to GitHub, even in a private repository - the
 * fallback values below are all non-secret and safe to publish; only
 * DB_PASS has an empty fallback, which will simply fail to connect
 * until it's set as an environment variable on the host.
 */

$dbHost = getenv('DB_HOST') ?: 'mysql-335f3a45-isaacoseiameyaw992-3a9a.c.aivencloud.com';
$dbPort = getenv('DB_PORT') ?: '27008';
$dbName = getenv('DB_NAME') ?: 'restaurant_db';
$dbUser = getenv('DB_USER') ?: 'avnadmin';
$dbPass = getenv('DB_PASS') ?: ''; // Set DB_PASS in your hosting platform's environment variables - never hardcode the real password here

$errors = [];
$success = null;

/**
 * Finds the smallest table that seats at least $guests people and has
 * no existing reservation within 90 minutes of the requested time on
 * that date. Mirrors TableDAO.findAvailableTable() in the Java app,
 * so both the desktop app and this webpage use the same logic.
 */
function findAvailableTable(PDO $pdo, int $guests, string $date, string $time): ?array {
    $sql = "SELECT id, table_number, seats FROM tables
            WHERE seats >= :guests
            AND id NOT IN (
                SELECT table_id FROM reservations
                WHERE reservation_date = :date
                AND table_id IS NOT NULL
                AND ABS(TIME_TO_SEC(TIMEDIFF(reservation_time, :time))) < 5400
            )
            ORDER BY seats ASC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['guests' => $guests, 'date' => $date, 'time' => $time]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot spam check: this field is invisible to real visitors via
    // CSS, but automated bots that blindly fill in every form field
    // will fill it too. If it's non-empty, silently pretend success
    // without touching the database, so the bot doesn't learn to adapt.
    if (!empty($_POST['website'] ?? '')) {
        $success = "Thanks! Your reservation request has been received.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $guests = (int)($_POST['guests'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '');

        if ($name === '') {
            $errors[] = "Please enter your name.";
        }
        if ($phone === '') {
            $errors[] = "Please enter a phone number.";
        }
        if ($guests < 1 || $guests > 20) {
            $errors[] = "Number of guests must be between 1 and 20.";
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = "Please choose a valid date.";
        } elseif ($date < date('Y-m-d')) {
            $errors[] = "Please choose a date that's today or later.";
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $errors[] = "Please choose a valid time.";
        }

        if (empty($errors)) {
            if ($dbPass === '') {
                $errors[] = "The reservation system isn't fully configured yet (missing database password). Please try calling us instead.";
            } else {
                try {
                    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        // Encrypts the connection (Aiven requires this), without
                        // validating the server's certificate against a trusted
                        // CA - the same trade-off made on the desktop app's side,
                        // kept consistent here for simplicity at this stage.
                        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                    ]);

                    $table = findAvailableTable($pdo, $guests, $date, $time);

                    $insert = $pdo->prepare(
                        "INSERT INTO reservations
                            (customer_name, phone_number, guests, table_id, reservation_date, reservation_time)
                         VALUES (:name, :phone, :guests, :table_id, :date, :time)"
                    );
                    $insert->execute([
                        'name' => $name,
                        'phone' => $phone,
                        'guests' => $guests,
                        'table_id' => $table['id'] ?? null,
                        'date' => $date,
                        'time' => $time . ':00',
                    ]);

                    if ($table) {
                        // Matches the desktop app's own behaviour: booking a
                        // reservation marks its assigned table Reserved
                        // immediately, regardless of how far in the future the
                        // date is. Known limitation carried over intentionally
                        // for consistency, not something new introduced here.
                        $pdo->prepare("UPDATE tables SET status = 'RESERVED' WHERE id = :id")
                            ->execute(['id' => $table['id']]);
                        $success = "Thanks, $name! Your table for $guests on $date at $time is booked.";
                    } else {
                        $success = "Thanks, $name! We've received your request for $guests on $date at $time, "
                                 . "but that time is fully booked right now - our staff will call you to "
                                 . "confirm or suggest another time.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Sorry, something went wrong on our end. Please try calling us instead, or try again shortly.";
                    // For real use: log $e->getMessage() somewhere private
                    // (a server error log) instead of showing raw DB errors
                    // to the customer.
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reserve a Table - Chop House</title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; background:#F4F6F5; margin:0; padding:0; color:#1E1E1E; }
  .wrap { max-width: 480px; margin: 40px auto; background:#fff; border-radius:16px; padding:32px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
  h1 { color:#154D37; margin-top:0; }
  p.sub { color:#6B7280; margin-top:-8px; }
  label { display:block; font-weight:600; margin:16px 0 6px; font-size:14px; }
  input { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #E2E5E4; border-radius:8px; font-size:14px; }
  button { margin-top:24px; width:100%; padding:12px; background:#1F6F50; color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:600; cursor:pointer; }
  button:hover { background:#154D37; }
  .msg-success { background:#E7F6EE; color:#1F6F50; padding:14px; border-radius:10px; margin-bottom:16px; }
  .msg-error { background:#FDECEC; color:#C0392B; padding:14px; border-radius:10px; margin-bottom:16px; }
  .honeypot { position:absolute; left:-9999px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>&#127869;&#65039; Reserve a Table</h1>
  <p class="sub">Chop House &mdash; book your table online</p>

  <?php if ($success): ?>
    <div class="msg-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="msg-error">
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" action="">
    <input type="text" name="website" class="honeypot" tabindex="-1" autocomplete="off">

    <label for="name">Full Name</label>
    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

    <label for="phone">Phone Number</label>
    <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

    <label for="guests">Number of Guests</label>
    <input type="number" id="guests" name="guests" min="1" max="20" required value="<?= htmlspecialchars($_POST['guests'] ?? '2') ?>">

    <label for="date">Date</label>
    <input type="date" id="date" name="date" required value="<?= htmlspecialchars($_POST['date'] ?? '') ?>">

    <label for="time">Time</label>
    <input type="time" id="time" name="time" required value="<?= htmlspecialchars($_POST['time'] ?? '') ?>">

    <button type="submit">Book My Table</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
