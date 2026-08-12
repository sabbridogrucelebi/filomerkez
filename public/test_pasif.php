<?php
$db = new SQLite3(__DIR__.'/../database/database.sqlite');
$results = $db->query("SELECT is_active, status FROM vehicles WHERE plate = '42 BHU 021'");
while ($row = $results->fetchArray()) {
    var_dump($row);
}
