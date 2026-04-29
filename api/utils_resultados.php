<?php
/**
 * Funções utilitárias para cálculo de pontos e reordenação
 */

function recalcularPontosEtapa($pdo, $etapa_id) {
    // 1. Buscar dados da Etapa para saber o Mês
    $stmt = $pdo->prepare("SELECT data FROM etapas WHERE id = ?");
    $stmt->execute([$etapa_id]);
    $etapa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$etapa) return false;

    $dataEtapa = new DateTime($etapa['data']);
    $mes = (int) $dataEtapa->format('m');

    $colunaMes = 'primeiro_semestre';
    if ($mes == 7) $colunaMes = 'julho';
    elseif ($mes == 8) $colunaMes = 'agosto';
    elseif ($mes == 9) $colunaMes = 'setembro';
    elseif ($mes == 10) $colunaMes = 'outubro';
    elseif ($mes == 11) $colunaMes = 'novembro';
    elseif ($mes == 12) $colunaMes = 'dezembro';

    // 2. Buscar todos os resultados da etapa que tenham posição > 0
    $stmtRes = $pdo->prepare("SELECT id, posicao, categoria, pontos_penalidade, desclassificado FROM resultados WHERE etapa_id = ? AND posicao > 0");
    $stmtRes->execute([$etapa_id]);
    $resultados = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

    // Prepara statements de busca e atualização para otimizar o loop
    $stmtPontos = $pdo->prepare("SELECT $colunaMes as pts FROM tabela_pontuacao WHERE categoria = ? AND posicao = ?");
    $stmtUpd = $pdo->prepare("UPDATE resultados SET pontos = ? WHERE id = ?");

    foreach ($resultados as $res) {
        $pontosBase = 0;
        
        // Se estiver desclassificado, pontos base é 0
        if ($res['desclassificado'] == 1) {
            $pontosBase = 0;
        } else {
            $stmtPontos->execute([$res['categoria'], $res['posicao']]);
            $resPontos = $stmtPontos->fetch(PDO::FETCH_ASSOC);
            if ($resPontos) {
                $pontosBase = (float) $resPontos['pts'];
            }
        }

        // Pontos finais = Pontos Base - Penalidades absolutas (O sistema abate, indepentende de ser salvo como 5 ou -5)
        $penalidadeAbater = abs((float)$res['pontos_penalidade']);
        $pontosFinais = max(0, $pontosBase - $penalidadeAbater);

        $stmtUpd->execute([$pontosFinais, $res['id']]);
    }
    
    return true;
}

function fecharBuracoPosicao($pdo, $etapa_id, $posicaoAntiga, $categoria = null) {
    if ($posicaoAntiga <= 0) return;
    
    if ($categoria) {
        $sql = "UPDATE resultados SET posicao = posicao - 1 WHERE etapa_id = ? AND categoria = ? AND posicao > ?";
        $pdo->prepare($sql)->execute([$etapa_id, $categoria, $posicaoAntiga]);
    } else {
        $sql = "UPDATE resultados SET posicao = posicao - 1 WHERE etapa_id = ? AND posicao > ?";
        $pdo->prepare($sql)->execute([$etapa_id, $posicaoAntiga]);
    }
}
