<?php
require __DIR__.'/bootstrap/app.php';
$kernel = app()->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$docs = \App\Models\Document::query()
    ->where('documentable_type', \App\Models\Fleet\Vehicle::class)
    ->whereNull('archived_at')
    ->whereNotNull('end_date')
    ->with('documentable')
    ->orderByDesc('end_date')
    ->get();

$filtered = $docs->filter(fn ($doc) => $doc->documentable && $doc->documentable->is_active);

echo "Total docs: " . $docs->count() . "\n";
echo "Filtered docs (active only): " . $filtered->count() . "\n";

foreach ($docs as $doc) {
    if ($doc->documentable && $doc->documentable->plate === '42 BHU 021') {
        echo "Found 42 BHU 021 document. Document Type: " . $doc->document_type . "\n";
        echo "Is Active property value: ";
        var_dump($doc->documentable->is_active);
        echo "Status property value: ";
        var_dump($doc->documentable->status);
    }
}
