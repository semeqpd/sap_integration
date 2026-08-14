<?php

// Cada aba: id do painel, rótulo e o path do ícone (SVG inline, sem
// biblioteca externa — seção 6 do ESTILO.md).
$tabs = [
    ['id' => 'links', 'label' => 'Vínculos', 'icon' => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7L12 19"/>'],
    ['id' => 'invoices', 'label' => 'Invoices', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8m8 4H8m2-8H8"/>'],
    ['id' => 'db', 'label' => 'Banco', 'icon' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>'],
];

?>

<nav class="tabs-bar">
    <?php foreach ($tabs as $index => $tab) { ?>
        <button class="tab-btn <?= e($index === 0 ? 'active' : '') ?>" data-tab="<?= e($tab['id']) ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <?= $tab['icon'] // markup de ícone declarado logo acima: sai cru de propósito ?>
            </svg>
            <?= e($tab['label']) ?>
        </button>
    <?php } ?>
</nav>
