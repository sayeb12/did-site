<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit("Not found."); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

echo '<meta charset="utf-8">';
echo '<h2>Raw values from DB (debug)</h2>';
echo '<pre>';
var_dump([
  'full_name' => $user['full_name'],
  'address' => $user['address'],
]);
echo '</pre>';

echo '<h2>Rendered HTML test</h2>';
// print same paragraph text exactly as generate_pdf would send it
echo '<p style="font-size:18px">';
echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8');
echo '</p>';
