<h1>Content Security Policy reports</h1>
<p>Total reports: <?= esc($total) ?></p>
<h2>By directive</h2>
<ul><?php foreach ($breakdown as $item): ?><li><?= esc($item['violated_directive']) ?>: <?= esc($item['count']) ?></li><?php endforeach ?></ul>
<h2>Latest reports</h2>
<table><thead><tr><th>Reported</th><th>Directive</th><th>Document</th><th>Blocked URI</th></tr></thead><tbody><?php foreach ($reports as $report): ?><tr><td><?= esc($report['reported_at']) ?></td><td><?= esc($report['violated_directive']) ?></td><td><?= esc($report['document_uri']) ?></td><td><?= esc($report['blocked_uri']) ?></td></tr><?php endforeach ?></tbody></table>
