<?php
/**
 * Helper para tratamento de imagens (Upload e Otimização)
 */

if (!function_exists('uploadAndOptimizeImage')) {
    /**
     * Faz upload de uma imagem, redimensiona se necessário e salva com compressão.
     * 
     * @param array $file O array $_FILES['input_name']
     * @param string $targetDir Diretório relativo à raiz (ex: 'images/fotos/pilotos/')
     * @param string $prefix Prefixo para o nome do arquivo
     * @param int $maxWidth Largura máxima desejada
     * @param int $maxHeight Altura máxima desejada
     * @param int $quality Qualidade JPEG (0-100)
     * @return string|false Caminho final do arquivo web ou false em caso de erro
     */
    /**
     * Remove o fundo de uma imagem usando a API Remove.bg
     */
    function removeImageBackground($imagePath) {
        $apiKey = envVar('REMOVE_BG_API_KEY');
        if (!$apiKey) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.remove.bg/v1.0/removebg');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Api-Key: ' . $apiKey));
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'image_file' => new CURLFile($imagePath),
            'size' => 'auto'
        ));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            return $result; // Retorna o conteúdo da imagem em binário (PNG)
        }
        
        return false;
    }

    /**
     * Faz upload de uma imagem, opcionalmente remove o fundo, e salva otimizada.
     */
    function uploadAndOptimizeImage($file, $targetDir, $prefix, $maxWidth = null, $maxHeight = null, $quality = 85, $removeBg = false) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $tmpPath = $file['tmp_name'];

        // Se a flag de remover fundo estiver ativa, tentamos a API
        if ($removeBg) {
            $bgRemovedData = removeImageBackground($tmpPath);
            if ($bgRemovedData) {
                // Salvar o resultado da API num arquivo temporário novo
                $tmpPath = $tmpPath . '_nobg.png';
                file_put_contents($tmpPath, $bgRemovedData);
            }
        }

        $fileInfo = getimagesize($tmpPath);
        if (!$fileInfo) {
            return false; // Não é uma imagem válida
        }

        list($width, $height, $type) = $fileInfo;
        
        // Criar recurso de imagem dependendo do tipo
        // Se removemos o fundo, o arquivo temporário agora é um PNG (retorno da API)
        switch ($type) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($tmpPath); break;
            case IMAGETYPE_PNG:  $src = imagecreatefrompng($tmpPath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($tmpPath); break;
            default: return false; // Formato não suportado
        }

        if (!$src) return false;

        // Se maxWidth ou maxHeight forem passados, redimensiona. Senão, mantém original.
        if ($maxWidth && $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            if ($ratio < 1) {
                $newWidth = floor($width * $ratio);
                $newHeight = floor($height * $ratio);
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Criar nova imagem
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparência (CRÍTICO para fotos sem fundo)
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
        imagefilledrectangle($dst, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Garantir que o diretório existe
        $fullTargetDir = __DIR__ . '/../' . ltrim($targetDir, '/');
        if (!is_dir($fullTargetDir)) {
            mkdir($fullTargetDir, 0777, true);
        }

        // Foco total na otimização de peso - Sempre salvar como WebP para manter transparência e peso baixo
        $newFileName = $prefix . '_' . time() . '.webp';
        $finalPath = $fullTargetDir . $newFileName;
        $webPath = '/' . ltrim($targetDir, '/') . $newFileName;

        $success = false;
        if (function_exists('imagewebp')) {
            $success = imagewebp($dst, $finalPath, $quality);
        } else {
            // Fallback para PNG se precisar manter transparência e não tiver WebP
            if ($removeBg || $type == IMAGETYPE_PNG) {
                $newFileName = $prefix . '_' . time() . '.png';
                $finalPath = $fullTargetDir . $newFileName;
                $webPath = '/' . ltrim($targetDir, '/') . $newFileName;
                $success = imagepng($dst, $finalPath, 8); // Compressão PNG 0-9
            } else {
                $newFileName = $prefix . '_' . time() . '.jpg';
                $finalPath = $fullTargetDir . $newFileName;
                $webPath = '/' . ltrim($targetDir, '/') . $newFileName;
                $success = imagejpeg($dst, $finalPath, $quality);
            }
        }

        imagedestroy($src);
        imagedestroy($dst);

        // Limpar arquivo temporário de remoção se existir
        if ($removeBg && file_exists($tmpPath) && strpos($tmpPath, '_nobg.png') !== false) {
            unlink($tmpPath);
        }

        return $success ? $webPath : false;
    }
}
