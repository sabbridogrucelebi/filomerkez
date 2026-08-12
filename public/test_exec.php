<?php
echo "proc_open: " . (function_exists('proc_open') ? 'yes' : 'no') . "\n";
echo "exec: " . (function_exists('exec') ? 'yes' : 'no') . "\n";
echo "system: " . (function_exists('system') ? 'yes' : 'no') . "\n";
