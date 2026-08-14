<?php /* Tela 1 — Vínculos: simular o cadastro do SAP e fechar as pendências. */ ?>
<section id="tab-links" class="tab-panel active">

    <div class="grid-2">
        <div class="card">
            <h2>Simular cadastro no SAP <span class="muted">(webhook)</span></h2>
            <p class="muted">
                O SAP chamaria <code>POST /webhook/sap/customer</code> ao cadastrar um cliente.
                Aqui você dispara na mão para ver o fluxo.
            </p>
            <div class="form-row">
                <div class="field">
                    <label for="wh-code">CardCode (código no SAP)</label>
                    <input id="wh-code" placeholder="C0104">
                </div>
                <div class="field grow">
                    <label for="wh-name">CardName (nome do cliente)</label>
                    <input id="wh-name" placeholder="Ambev Jaguariúna">
                </div>
                <div class="field">
                    <label for="wh-country">País da filial</label>
                    <select id="wh-country">
                        <option value="php">Filipinas (Jaz)</option>
                        <option value="eua">Estados Unidos (Xero)</option>
                    </select>
                </div>
            </div>
            <button class="btn" id="wh-send">Disparar webhook</button>
        </div>

        <div class="card">
            <h2>Fluxo no banco <span class="muted">(última ação)</span></h2>
            <div id="steps-links" class="steps-box">
                <div class="state-box">
                    <span>Nenhuma ação ainda — dispare um webhook ou resolva uma pendência.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Pendências de vínculo <span class="muted">— decisões esperando uma pessoa</span></h2>
        <div class="table-toolbar" id="tools-pending"></div>
        <div class="table-wrapper"><table id="tbl-pending"></table></div>
        <div class="table-pager" id="pager-pending"></div>
    </div>

    <div class="card">
        <h2>Entidades e seus códigos <span class="muted">— o de-para consolidado</span></h2>
        <div class="table-toolbar" id="tools-entities"></div>
        <div class="table-wrapper"><table id="tbl-entities"></table></div>
        <div class="table-pager" id="pager-entities"></div>
    </div>

</section>
