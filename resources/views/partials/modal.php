<?php /* Modal de vínculo manual: escolher do catálogo ou cadastrar um novo. */ ?>
<div class="modal-overlay" id="modal">
    <div class="modal-box">
        <div class="modal-header">
            <b id="modal-title">Vincular</b>
            <button class="btn secondary" id="modal-close">Fechar</button>
        </div>

        <div class="modal-body">
            <p class="muted" id="modal-sub"></p>

            <?php /* modo 1: escolher um registro existente do catálogo */ ?>
            <div id="modal-existing">
                <div class="field grow">
                    <label for="modal-search">Buscar no catálogo</label>
                    <div class="table-search">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
                        </svg>
                        <input type="search" id="modal-search" class="search-input"
                               placeholder="Digite parte do nome ou do código…" autocomplete="off">
                    </div>
                </div>

                <div class="field grow">
                    <label for="modal-select">
                        Registro correspondente <span class="muted" id="modal-count"></span>
                    </label>
                    <select id="modal-select" size="8"></select>
                </div>

                <button class="btn secondary small" id="modal-toggle-new" type="button">
                    + Não achei — adicionar novo
                </button>
            </div>

            <?php /* modo 2: cadastrar um registro novo (demo — sem API real da filial) */ ?>
            <div id="modal-new" hidden>
                <div class="field grow">
                    <label for="modal-new-name">Nome do novo registro</label>
                    <input id="modal-new-name" placeholder="Nome do cliente na filial">
                </div>
                <p class="muted">
                    Cadastra no catálogo e vincula. Demonstração — a criação real na filial
                    ainda não tem API.
                </p>
                <button class="btn secondary small" id="modal-toggle-existing" type="button">
                    ← Voltar para a lista
                </button>
            </div>

            <div class="field grow">
                <label for="modal-by">Quem está decidindo</label>
                <input id="modal-by" placeholder="seu.email@semeq.com">
            </div>

            <button class="btn" id="modal-save">Salvar vínculo</button>
        </div>
    </div>
</div>
