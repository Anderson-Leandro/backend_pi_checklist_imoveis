<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    /**
     * Gera o PDF do checklist e retorna o conteúdo binário.
     *
     * @param array $checklist     Dados do checklist com itens
     * @param array $contrato      Dados do contrato
     * @param array $imovel        Dados do imóvel
     * @param array|null $endereco Endereço do imóvel
     * @param array $locatario     Dados do locatário
     * @param array $vistoriador   Dados do vistoriador
     * @param array|null $aceite   Dados do aceite (se houver)
     */
    public function gerarChecklistPdf(
        array $checklist,
        array $contrato,
        array $imovel,
        array|null $endereco,
        array $locatario,
        array $vistoriador,
        array|null $aceite
    ): string {
        $html = $this->montarHtml($checklist, $contrato, $imovel, $endereco, $locatario, $vistoriador, $aceite);

        $opcoes = new Options();
        $opcoes->set('isRemoteEnabled', true);
        $opcoes->set('isHtml5ParserEnabled', true);
        $opcoes->set('defaultFont', 'sans-serif');

        $dompdf = new Dompdf($opcoes);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function montarHtml(
        array $checklist,
        array $contrato,
        array $imovel,
        array|null $endereco,
        array $locatario,
        array $vistoriador,
        array|null $aceite
    ): string {
        $enderecoTexto = $endereco !== null
            ? "{$endereco['rua']}, {$endereco['numero']}"
                . ($endereco['complemento'] ? " — {$endereco['complemento']}" : '')
                . " — {$endereco['cidade']}/{$endereco['estado']} — CEP {$endereco['cep']}"
            : 'Não cadastrado';

        $statusLabel = match ($checklist['status']) {
            'em_preenchimento' => 'Em preenchimento',
            'pendente_aceite'  => 'Pendente de aceite',
            'aceito'           => 'Aceito',
            'rejeitado'        => 'Rejeitado',
            'pendente_revisao' => 'Pendente de revisão',
            default            => $checklist['status'],
        };

        $tipoLabel = $checklist['tipo'] === 'inicial' ? 'Vistoria Inicial' : 'Vistoria de Encerramento';

        $dataVistoria = $checklist['data_vistoria'] !== null
            ? date('d/m/Y H:i', strtotime($checklist['data_vistoria']))
            : 'Não informada';

        $itensHtml = $this->montarItensHtml($checklist['itens'] ?? []);

        $aceiteHtml = '';
        if ($aceite !== null) {
            $aceiteStatusLabel = $aceite['status'] === 'aceito' ? 'Aceito' : 'Rejeitado';
            $aceiteDataFormatada = date('d/m/Y H:i', strtotime($aceite['created_at']));
            $motivoHtml = $aceite['motivo_rejeicao']
                ? "<p><strong>Motivo da rejeição:</strong> " . htmlspecialchars($aceite['motivo_rejeicao']) . "</p>"
                : '';
            $aceiteHtml = "
            <div class=\"secao\">
                <h2>Aceite do Locatário</h2>
                <table>
                    <tr><th>Status</th><td>{$aceiteStatusLabel}</td></tr>
                    <tr><th>Data</th><td>{$aceiteDataFormatada}</td></tr>
                    <tr><th>Locatário</th><td>" . htmlspecialchars($locatario['nome']) . "</td></tr>
                </table>
                {$motivoHtml}
            </div>";
        }

        return "<!DOCTYPE html>
<html lang=\"pt-BR\">
<head>
<meta charset=\"UTF-8\">
<title>Checklist — One Check</title>
<style>
  body { font-family: sans-serif; font-size: 12px; color: #333; margin: 20px; }
  h1 { font-size: 18px; color: #1a1a2e; border-bottom: 2px solid #1a1a2e; padding-bottom: 6px; }
  h2 { font-size: 14px; color: #444; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
  h3 { font-size: 13px; color: #555; margin: 12px 0 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  table th { text-align: left; width: 35%; background: #f5f5f5; padding: 5px 8px; border: 1px solid #ddd; font-weight: bold; }
  table td { padding: 5px 8px; border: 1px solid #ddd; }
  .secao { margin-bottom: 24px; }
  .item-vistoria { background: #fafafa; border: 1px solid #e0e0e0; border-radius: 4px; padding: 8px 12px; margin-bottom: 8px; }
  .estado { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
  .estado-otimo  { background: #d4edda; color: #155724; }
  .estado-bom    { background: #cce5ff; color: #004085; }
  .estado-regular{ background: #fff3cd; color: #856404; }
  .estado-ruim   { background: #f8d7da; color: #721c24; }
  .badge-status  { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: bold; }
  .status-aceito         { background: #d4edda; color: #155724; }
  .status-pendente       { background: #fff3cd; color: #856404; }
  .status-rejeitado      { background: #f8d7da; color: #721c24; }
  .foto-url { font-size: 10px; color: #666; word-break: break-all; }
  .rodape { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 10px; color: #888; text-align: center; }
</style>
</head>
<body>

<h1>Relatório de Checklist de Vistoria</h1>
<p style=\"color:#666;\">Gerado em " . date('d/m/Y H:i') . " — One Check API</p>

<div class=\"secao\">
  <h2>Dados do Imóvel</h2>
  <table>
    <tr><th>Tipo</th><td>" . htmlspecialchars($imovel['tipo']) . "</td></tr>
    <tr><th>Tamanho</th><td>" . htmlspecialchars($imovel['tamanho']) . "</td></tr>
    <tr><th>Garagem</th><td>" . ($imovel['garagem'] ? "Sim ({$imovel['garagem_vagas']} vaga(s))" : 'Não') . "</td></tr>
    <tr><th>Endereço</th><td>" . htmlspecialchars($enderecoTexto) . "</td></tr>
  </table>
</div>

<div class=\"secao\">
  <h2>Dados do Contrato</h2>
  <table>
    <tr><th>ID do Contrato</th><td>" . htmlspecialchars($contrato['id']) . "</td></tr>
    <tr><th>Locatário</th><td>" . htmlspecialchars($locatario['nome']) . " (" . htmlspecialchars($locatario['email']) . ")</td></tr>
    <tr><th>Período</th><td>" . date('d/m/Y', strtotime($contrato['data_inicio'])) . " até " . date('d/m/Y', strtotime($contrato['data_fim'])) . "</td></tr>
    <tr><th>Status</th><td>" . htmlspecialchars($contrato['status']) . "</td></tr>
  </table>
</div>

<div class=\"secao\">
  <h2>Dados da Vistoria</h2>
  <table>
    <tr><th>Tipo de Vistoria</th><td>{$tipoLabel}</td></tr>
    <tr><th>Vistoriador</th><td>" . htmlspecialchars($vistoriador['nome']) . "</td></tr>
    <tr><th>Data da Vistoria</th><td>{$dataVistoria}</td></tr>
    <tr><th>Status</th><td><span class=\"badge-status\">{$statusLabel}</span></td></tr>
  </table>
</div>

<div class=\"secao\">
  <h2>Itens Vistoriados</h2>
  {$itensHtml}
</div>

{$aceiteHtml}

<div class=\"rodape\">
  One Check API — Relatório gerado automaticamente em " . date('d/m/Y H:i:s') . "
</div>

</body>
</html>";
    }

    private function montarItensHtml(array $itens): string
    {
        if (empty($itens)) {
            return '<p><em>Nenhum item registrado.</em></p>';
        }

        $html = '';
        foreach ($itens as $item) {
            $estadoClass = match ($item['estado']) {
                'otimo'   => 'estado-otimo',
                'bom'     => 'estado-bom',
                'regular' => 'estado-regular',
                'ruim'    => 'estado-ruim',
                default   => '',
            };
            $estadoLabel = match ($item['estado']) {
                'otimo'   => 'Ótimo',
                'bom'     => 'Bom',
                'regular' => 'Regular',
                'ruim'    => 'Ruim',
                default   => $item['estado'],
            };

            $observacaoHtml = $item['observacao']
                ? '<p style="margin:4px 0 0;"><strong>Obs:</strong> ' . htmlspecialchars($item['observacao']) . '</p>'
                : '';

            $fotosHtml = '';
            foreach ($item['fotos'] ?? [] as $foto) {
                $fotosHtml .= '<p class="foto-url">📷 ' . htmlspecialchars($foto['url']) . '</p>';
            }

            $html .= "
            <div class=\"item-vistoria\">
              <strong>Cômodo:</strong> " . htmlspecialchars($item['comodo_id']) . "
              &nbsp;|&nbsp;
              <strong>Item:</strong> " . htmlspecialchars($item['item_vistoria_id']) . "
              &nbsp;|&nbsp;
              <strong>Estado:</strong> <span class=\"estado {$estadoClass}\">{$estadoLabel}</span>
              {$observacaoHtml}
              {$fotosHtml}
            </div>";
        }

        return $html;
    }
}
