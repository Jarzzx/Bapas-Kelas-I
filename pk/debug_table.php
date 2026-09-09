<?php
require_once '../shared/config/database.php';
$conn = getDBConnection();

echo "<h2>Table Structure: laporan_pengawasan</h2>";
$res = $conn->query("DESCRIBE laporan_pengawasan");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while($row = $res->fetch_assoc()) {
    echo "<tr>";
    foreach($row as $val) echo "<td>$val</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Latest 5 Rows</h2>";
$res = $conn->query("SELECT * FROM laporan_pengawasan ORDER BY id DESC LIMIT 5");
echo "<table border='1'><tr><th>ID</th><th>Klien ID</th><th>Nomor SK</th></tr>";
while($row = $res->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['klien_id']}</td>";
    echo "<td>{$row['nomor_sk']}</td>";
    echo "</tr>";
}
echo "</table>";
?>