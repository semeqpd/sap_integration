<?php /* Tela 2 — Invoices: poll das filiais e o staging. */ ?>
<section id="tab-invoices" class="tab-panel">

    <div class="grid-2">
        <div class="card">
            <h2>Invoices das filiais <span class="muted">(Jaz · Filipinas &nbsp;+&nbsp; Xero · EUA)</span></h2>
            <p class="muted">
                O poller consulta Jaz e Xero periodicamente (diário/hora em produção).
                Você pode forçar a verificação das duas ou injetar uma invoice de teste
                (cliente Pacific Trade, já vinculado) — ela é lançada de verdade no SAP.
            </p>
            <div class="form-row">
                <button class="btn secondary" id="inv-poll">Verificar agora (Jaz + Xero)</button>
                <button class="btn" id="inv-demo">Injetar invoice de teste</button>
            </div>
        </div>

        <div class="card">
            <h2>Fluxo no banco <span class="muted">(última ação)</span></h2>
            <div id="steps-invoices" class="steps-box">
                <div class="state-box"><span>Nenhuma ação ainda.</span></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>invoice_staging <span class="muted">— tudo que chegou da filial</span></h2>
        <div class="table-toolbar" id="tools-invoices"></div>
        <div class="table-wrapper"><table id="tbl-invoices"></table></div>
        <div class="table-pager" id="pager-invoices"></div>
    </div>

</section>
