$(document).ready(function() {

    // Carrega a tabela assim que a página abre
    carregarDocumentos();

    // Configura o poling para atualizar a tabela a cada 5 segundos
    setInterval(carregarDocumentos, 5000);

    // Evento de submit do formulário via AJAX
    $('#uploadForm').on('submit', function(e) {
        e.preventDefault(); // Impede o recarregamento da página

        let btn = $('#btnUpload');
        let statusDiv = $('#uploadStatus');
        
        let fileInput = $('#arquivo')[0];
        if(fileInput.files.length === 0) {
            statusDiv.html('<div class="alert alert-warning">Selecione um arquivo.</div>');
            return;
        }

        let formData = new FormData(this);

        // Desabilita botão durante o envio
        btn.prop('disabled', true).text('Enviando...');
        statusDiv.html('<div class="alert alert-info">Fazendo upload...</div>');

        $.ajax({
            url: 'upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btn.prop('disabled', false).text('Enviar Arquivo');
                $('#arquivo').val(''); // limpa o input
                
                if(response.success) {
                    statusDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    // Atualiza a tabela imediatamente
                    carregarDocumentos();
                } else {
                    statusDiv.html('<div class="alert alert-danger">Erro: ' + response.message + '</div>');
                }

                // Remove o aviso após 5 segundos
                setTimeout(() => statusDiv.html(''), 5000);
            },
            error: function() {
                btn.prop('disabled', false).text('Enviar Arquivo');
                statusDiv.html('<div class="alert alert-danger">Ocorreu um erro ao comunicar com o servidor.</div>');
            }
        });
    });

    // Função que busca dados do banco e popula a tabela
    function carregarDocumentos() {
        $.ajax({
            url: 'list.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    let tbody = $('#tabelaDocumentos tbody');
                    tbody.empty(); // limpa tabela atual

                    if(response.data.length === 0) {
                        tbody.append('<tr><td colspan="6" class="text-center text-muted">Nenhum documento na fila</td></tr>');
                        return;
                    }

                    // Itera cada linha
                    $.each(response.data, function(index, doc) {
                        let badgeClass = getBadgeStatus(doc.status);
                        
                        let tipoDocumento = doc.tipo_documento ? doc.tipo_documento : '<span class="text-muted">Aguardando IA...</span>';

                        let tr = $('<tr>');
                        tr.append('<td>#' + doc.id + '</td>');
                        tr.append('<td>' + escapeHtml(doc.nome_arquivo) + '</td>');
                        tr.append('<td><span class="badge ' + badgeClass + '">' + doc.status.toUpperCase() + '</span></td>');
                        tr.append('<td>' + escapeHtml(tipoDocumento) + '</td>');
                        tr.append('<td>' + doc.custo_estimado + '</td>');
                        tr.append('<td>' + doc.data_criacao + '</td>');
                        
                        tbody.append(tr);
                    });
                }
            }
        });
    }

    // Helper para cor da badge
    function getBadgeStatus(status) {
        switch(status) {
            case 'pendente': return 'bg-secondary';
            case 'processando': return 'bg-warning text-dark';
            case 'concluido': return 'bg-success';
            case 'erro': return 'bg-danger';
            default: return 'bg-light text-dark';
        }
    }

    // Prevenir XSS
    function escapeHtml(text) {
        if (!text) return text;
        return text
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

});