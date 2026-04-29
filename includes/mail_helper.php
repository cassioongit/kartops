<?php
/**
 * Helper para envio de emails usando PHPMailer e Gmail SMTP
 */

// Proteção contra acesso direto
require_once __DIR__ . '/security.php';
blockDirectAccess(__FILE__);
// Carregar configurações
require_once __DIR__ . '/../config/mail_config.php';

// Carregar classes do PHPMailer manualmente (sem Composer)
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envia notificação para o Admin
 */
function sendAdminNotification($subject, $bodyContent)
{
    $adminEmail = 'deftones1971@gmail.com';
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'KartOps System');
        $mail->addAddress($adminEmail);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = "<h3>🔔 Notificação</h3><p>$bodyContent</p><hr><small>KartOps System</small>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Envia email de boas-vindas para novos usuários
 */
function sendWelcomeEmail($toEmail, $userName)
{
    $mail = new PHPMailer(true);
    $loginUrl = APP_URL . '/index.php';
    $year = date('Y');
    $baseDir = __DIR__ . '/../'; // Raiz do projeto

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'KartOps');
        $mail->addAddress($toEmail, $userName);

        // Imagens para embed (CID)
        $sponsors = [
            'logo_camp' => 'images/logo-campeonato.png',
            'ainext' => 'images/logos/sponsors/ainext-logo.png',
            'novadutra' => 'images/logos/sponsors/novadutra.png',
            'cardoso' => 'images/logos/sponsors/cardoso-logo.png',
            'botequim' => 'images/logos/sponsors/botequimgp-logo.jpg',
            'autoradio' => 'images/logos/sponsors/autoradio-logo.png',
            'green' => 'images/logos/sponsors/greensolutions-logo.png',
            'kda' => 'images/logos/sponsors/logo-kda-raceweare-monocromatica.png'
        ];

        // Anexar imagens inline
        foreach ($sponsors as $cid => $path) {
            $fullPath = $baseDir . $path;
            if (file_exists($fullPath)) {
                $mail->addEmbeddedImage($fullPath, $cid, basename($path));
            }
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '🏎️ Bem-vindo(a) ao KartOps!';

        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 8px 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px 30px; color: #333333; line-height: 1.8; }
                .content h2 { color: #667eea; margin-top: 0; }
                .highlight-box { background: linear-gradient(135deg, #f8f9ff, #eef1ff); border-left: 4px solid #667eea; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .highlight-box ul { margin: 10px 0 0; padding-left: 20px; }
                .highlight-box li { margin: 8px 0; }
                .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; text-align: center; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏎️ KartOps</h1>
                    <p>Sistema de Gestão de Campeonatos</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <img src='cid:logo_camp' alt='Campeonato' style='max-width: 200px; height: auto;'>
                    </div>
                    <h2>Olá, {$userName}! 🎉</h2>
                    <p>É com grande prazer que damos as <strong>boas-vindas</strong> ao KartOps!</p>
                    <p>Agradecemos imensamente a você — seja piloto ou visitante ilustre — por se juntar ao nosso sistema. Sua presença faz toda a diferença!</p>

                    <div class='highlight-box'>
                        <strong>📬 O que você receberá:</strong>
                        <ul>
                            <li>🗓️ <strong>Alterações no calendário</strong> de etapas</li>
                            <li>📋 <strong>Atualizações de regras</strong> e regulamentos</li>
                            <li>🎟️ <strong>Cupons de desconto</strong> exclusivos</li>
                            <li>📢 <strong>Avisos gerais</strong> e novidades do campeonato</li>
                        </ul>
                    </div>

                    <p style='background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; font-size: 14px; color: #856404;'>
                        ⚠️ <strong>Dica importante:</strong> Para garantir que nossas mensagens não caiam no spam, adicione <strong>oskarteiro@gmail.com</strong> aos seus contatos de email!
                    </p>

                    <p><strong>Nota:</strong> Em breve um administrador vinculará seu usuário a um piloto, caso você já seja inscrito no campeonato.</p>

                    <p>Fique ligado! Vamos manter você informado sobre tudo que acontece.</p>

                    <p style='text-align: center;'>
                        <a href='{$loginUrl}' class='btn' style='color: #ffffff !important;'>Acessar o KartOps</a>
                    </p>
                </div>
                <div style='background: #f0f0f5; padding: 30px 20px; text-align: center; border-top: 2px solid #667eea;'>
                    <p style='font-size: 15px; color: #555; margin: 0 0 8px;'><strong>🤝 Apoie nossos patrocinadores!</strong></p>
                    <p style='font-size: 13px; color: #777; margin: 0 0 20px;'>Clique nos logos abaixo e visite seus sites e redes sociais. Eles fazem o campeonato acontecer!</p>
                    <table cellpadding='0' cellspacing='0' border='0' align='center' style='margin: 0 auto;'>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://ainext.com.br' target='_blank'><img src='cid:ainext' alt='AINEXT' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://novadutramultimarcas.com.br' target='_blank'><img src='cid:novadutra' alt='Nova Dutra Multimarcas' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><img src='cid:cardoso' alt='Cardoso Funilaria' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://www.botequimgpkart.com.br/' target='_blank'><img src='cid:botequim' alt='Botequim GP Kart' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://autoradiopodcast.com.br' target='_blank'><img src='cid:autoradio' alt='AutoRadio Podcast' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://gsar.com.br' target='_blank'><img src='cid:green' alt='Green Solutions' style='height: 40px; max-width: 120px;'></a></td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 8px 12px; text-align: center;'><img src='cid:kda' alt='KDA Racewear' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                    </table>
                </div>
                <div class='footer'>
                    <p>Portal KartOps © {$year}</p>
                    <p style='margin-top: 5px;'>Você recebeu este email porque se cadastrou no KartOps.<br>Caso não deseje mais receber comunicações, atualize suas preferências no sistema.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $body;
        $mail->AltBody = "Olá, {$userName}! Bem-vindo(a) ao KartOps! Agradecemos por se juntar ao nosso sistema. Você receberá novidades, cupons de desconto e avisos sobre o campeonato. Acesse: {$loginUrl}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[KartOps] Erro ao enviar email de boas-vindas para {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envia email informando vinculação de piloto
 */
function sendPilotLinkedEmail($toEmail, $userName)
{
    $mail = new PHPMailer(true);
    $loginUrl = APP_URL . '/index.php';
    $year = date('Y');
    $baseDir = __DIR__ . '/../';

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'KartOps');
        $mail->addAddress($toEmail, $userName);

        // Imagens para embed (CID)
        $sponsors = [
            'logo_camp' => 'images/logo-campeonato.png',
            'ainext' => 'images/logos/sponsors/ainext-logo.png',
            'novadutra' => 'images/logos/sponsors/novadutra.png',
            'cardoso' => 'images/logos/sponsors/cardoso-logo.png',
            'botequim' => 'images/logos/sponsors/botequimgp-logo.jpg',
            'autoradio' => 'images/logos/sponsors/autoradio-logo.png',
            'green' => 'images/logos/sponsors/greensolutions-logo.png',
            'kda' => 'images/logos/sponsors/logo-kda-raceweare-monocromatica.png'
        ];

        foreach ($sponsors as $cid => $path) {
            $fullPath = $baseDir . $path;
            if (file_exists($fullPath)) {
                $mail->addEmbeddedImage($fullPath, $cid, basename($path));
            }
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '🏎️ Sua conta foi vinculada a um piloto!';

        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #10b981, #059669); padding: 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 8px 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px 30px; color: #333333; line-height: 1.8; }
                .highlight-box { background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-left: 4px solid #10b981; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .highlight-box ul { margin: 10px 0 0; padding-left: 20px; }
                .highlight-box li { margin: 8px 0; }
                .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #10b981, #059669); color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; text-align: center; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏎️ Perfil Vinculado!</h1>
                    <p>KartOps — Sistema de Gestão</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <img src='cid:logo_camp' alt='Campeonato' style='max-width: 200px; height: auto;'>
                    </div>
                    <h2>Olá, {$userName}! 🎉</h2>
                    <p>Temos uma ótima notícia! Um administrador acabou de <strong>vincular sua conta de usuário a um perfil de piloto</strong> existente no campeonato.</p>

                    <div class='highlight-box'>
                        <strong>🚀 O que isso significa?</strong>
                        <p>Agora você tem acesso total às ferramentas do piloto! Através do seu painel, você pode:</p>
                        <ul>
                            <li>✏️ <strong>Alterar seus dados</strong> e foto de perfil</li>
                            <li>📹 <strong>Subir Onboards</strong> (vídeos) das suas corridas</li>
                            <li>💸 <strong>Enviar comprovantes</strong> de pagamento</li>
                        </ul>
                    </div>

                    <p>Acesse agora mesmo para conferir as novidades no seu perfil.</p>

                    <p style='text-align: center;'>
                        <a href='{$loginUrl}' class='btn' style='color: #ffffff !important;'>Acessar Painel do Piloto</a>
                    </p>
                </div>
                <div style='background: #f0f0f5; padding: 30px 20px; text-align: center; border-top: 2px solid #10b981;'>
                    <p style='font-size: 15px; color: #555; margin: 0 0 8px;'><strong>🤝 Apoie nossos patrocinadores!</strong></p>
                    <table cellpadding='0' cellspacing='0' border='0' align='center' style='margin: 0 auto;'>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://ainext.com.br' target='_blank'><img src='cid:ainext' alt='AINEXT' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://novadutramultimarcas.com.br' target='_blank'><img src='cid:novadutra' alt='Nova Dutra Multimarcas' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><img src='cid:cardoso' alt='Cardoso Funilaria' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://www.botequimgpkart.com.br/' target='_blank'><img src='cid:botequim' alt='Botequim GP Kart' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://autoradiopodcast.com.br' target='_blank'><img src='cid:autoradio' alt='AutoRadio Podcast' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://gsar.com.br' target='_blank'><img src='cid:green' alt='Green Solutions' style='height: 40px; max-width: 120px;'></a></td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 8px 12px; text-align: center;'><img src='cid:kda' alt='KDA Racewear' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                    </table>
                </div>
                <div class='footer'>
                    <p>Portal KartOps © {$year}</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $body;
        $mail->AltBody = "Sua conta foi vinculada a um piloto! Agora você pode alterar dados, subir onboards e enviar comprovantes. Acesse: {$loginUrl}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[KartOps] Erro ao enviar email de vínculo para {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Envia notificação de alteração de etapa para UM ÚNICO usuário
 */
function sendEtapaNotificationToUser($action, $etapaData, $toEmail, $toName, $adminNome = 'Administrador')
{
    $mail = new PHPMailer(true);
    $year = date('Y');
    $baseDir = __DIR__ . '/../';
    $loginUrl = APP_URL . '/etapas.php';

    $actionLabels = [
        'create' => ['emoji' => '🆕', 'titulo' => 'Nova Etapa Criada!', 'assunto' => 'Nova etapa no calendário!', 'cor' => '#10b981'],
        'update' => ['emoji' => '📝', 'titulo' => 'Etapa Atualizada', 'assunto' => 'Etapa atualizada no calendário', 'cor' => '#f59e0b'],
        'delete' => ['emoji' => '❌', 'titulo' => 'Etapa Removida', 'assunto' => 'Etapa removida do calendário', 'cor' => '#ef4444']
    ];
    $label = $actionLabels[$action] ?? $actionLabels['update'];

    $dataFormatada = '';
    if (!empty($etapaData['data'])) {
        $formatter = new \IntlDateFormatter('pt_BR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $dataFormatada = $formatter->format(new \DateTime($etapaData['data']));
    }
    $horaFormatada = !empty($etapaData['hora']) ? date('H:i', strtotime($etapaData['hora'])) : '';

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_USER, 'KartOps');
        $mail->addAddress($toEmail, $toName);

        $sponsors = [
            'logo_camp' => 'images/logo-campeonato.png',
            'ainext' => 'images/logos/sponsors/ainext-logo.png',
            'novadutra' => 'images/logos/sponsors/novadutra.png',
            'cardoso' => 'images/logos/sponsors/cardoso-logo.png',
            'botequim' => 'images/logos/sponsors/botequimgp-logo.jpg',
            'autoradio' => 'images/logos/sponsors/autoradio-logo.png',
            'green' => 'images/logos/sponsors/greensolutions-logo.png',
            'kda' => 'images/logos/sponsors/logo-kda-raceweare-monocromatica.png'
        ];
        foreach ($sponsors as $cid => $path) {
            $fullPath = $baseDir . $path;
            if (file_exists($fullPath)) {
                $mail->addEmbeddedImage($fullPath, $cid, basename($path));
            }
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "🏎️ {$label['assunto']} — " . ($etapaData['nome'] ?? 'Etapa');
        $cor = $label['cor'];
        $en = htmlspecialchars($etapaData['nome'] ?? '');
        $ek = htmlspecialchars($etapaData['kartodromo'] ?? '');
        $et = htmlspecialchars($etapaData['tipo_etapa'] ?? '');

        $body = "
        <html><head><style>
            body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f4f4f4;margin:0;padding:0}
            .container{max-width:600px;margin:20px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,.1)}
            .header{background:linear-gradient(135deg,{$cor},{$cor}cc);padding:30px;text-align:center;color:#fff}
            .header h1{margin:0;font-size:28px;font-weight:bold}.header p{margin:8px 0 0;font-size:14px;opacity:.9}
            .content{padding:40px 30px;color:#333;line-height:1.8}
            .details-box{background:#f8f9fa;border-radius:10px;padding:20px;margin:20px 0;border:1px solid #e9ecef}
            .btn{display:inline-block;padding:14px 28px;background:linear-gradient(135deg,{$cor},{$cor}cc);color:#fff;text-decoration:none;border-radius:50px;font-weight:bold;margin-top:20px;text-align:center;box-shadow:0 4px 15px rgba(0,0,0,.2)}
            .footer{background:#f9f9f9;padding:20px;text-align:center;font-size:12px;color:#999;border-top:1px solid #eee}
        </style></head><body>
        <div class='container'>
            <div class='header'><h1>{$label['emoji']} {$label['titulo']}</h1><p>KartOps — Calendário de Etapas</p></div>
            <div class='content'>
                <div style='text-align:center;margin-bottom:20px'><img src='cid:logo_camp' alt='Campeonato' style='max-width:200px;height:auto'></div>
                <p>Olá, <strong>{$toName}</strong>!</p>
                <p>Uma alteração foi feita no calendário do campeonato por <strong>{$adminNome}</strong>:</p>
                <div class='details-box'>
                    <table cellpadding='8' cellspacing='0' border='0' style='width:100%;border-collapse:collapse'>
                        <tr style='border-bottom:1px solid #eee'><td style='font-weight:bold;color:#555;width:120px'>📌 Etapa</td><td style='color:#333'>{$en}</td></tr>
                        <tr style='border-bottom:1px solid #eee'><td style='font-weight:bold;color:#555'>🏁 Tipo</td><td style='color:#333'>{$et}</td></tr>
                        <tr style='border-bottom:1px solid #eee'><td style='font-weight:bold;color:#555'>📅 Data</td><td style='color:#333'>{$dataFormatada}</td></tr>
                        <tr style='border-bottom:1px solid #eee'><td style='font-weight:bold;color:#555'>🕐 Horário</td><td style='color:#333'>{$horaFormatada}</td></tr>
                        <tr><td style='font-weight:bold;color:#555'>📍 Local</td><td style='color:#333'>{$ek}</td></tr>
                    </table>
                </div>
                <p style='text-align:center'><a href='{$loginUrl}' class='btn' style='color:#fff!important'>Ver Calendário Completo</a></p>
            </div>
            <div style='background:#f0f0f5;padding:30px 20px;text-align:center;border-top:2px solid {$cor}'>
                <p style='font-size:15px;color:#555;margin:0 0 8px'><strong>🤝 Apoie nossos patrocinadores!</strong></p>
                <table cellpadding='0' cellspacing='0' border='0' align='center' style='margin:0 auto'>
                    <tr>
                        <td style='padding:8px 12px'><a href='https://ainext.com.br' target='_blank'><img src='cid:ainext' alt='AINEXT' style='height:40px;max-width:120px'></a></td>
                        <td style='padding:8px 12px'><a href='https://novadutramultimarcas.com.br' target='_blank'><img src='cid:novadutra' alt='Nova Dutra' style='height:40px;max-width:120px'></a></td>
                        <td style='padding:8px 12px'><img src='cid:cardoso' alt='Cardoso' style='height:40px;max-width:120px'></td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px'><a href='https://www.botequimgpkart.com.br/' target='_blank'><img src='cid:botequim' alt='Botequim GP' style='height:40px;max-width:120px'></a></td>
                        <td style='padding:8px 12px'><a href='https://autoradiopodcast.com.br' target='_blank'><img src='cid:autoradio' alt='AutoRadio' style='height:40px;max-width:120px'></a></td>
                        <td style='padding:8px 12px'><a href='https://gsar.com.br' target='_blank'><img src='cid:green' alt='Green Solutions' style='height:40px;max-width:120px'></a></td>
                    </tr>
                    <tr><td colspan='3' style='padding:8px 12px;text-align:center'><img src='cid:kda' alt='KDA Racewear' style='height:40px;max-width:120px'></td></tr>
                </table>
            </div>
            <div class='footer'><p>Portal KartOps © {$year}</p><p style='margin-top:5px'>Você recebeu este email porque está cadastrado no KartOps.</p></div>
        </div>
        </body></html>";

        $mail->Body = $body;
        $mail->AltBody = "Olá, {$toName}! {$label['titulo']}: {$en} - {$dataFormatada} às {$horaFormatada} em {$ek}. Acesse: {$loginUrl}";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("[KartOps] Erro ao enviar notificação para {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }



    // Buscar todos os emails de usuários ativos
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT email, nome FROM usuarios WHERE email IS NOT NULL AND email != ''");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($usuarios))
            return false;
    } catch (\PDOException $e) {
        error_log("[KartOps] Erro ao buscar usuários para notificação: " . $e->getMessage());
        return false;
    }

    // Definir assunto e título por tipo de ação
    $actionLabels = [
        'create' => ['emoji' => '🆕', 'titulo' => 'Nova Etapa Criada!', 'assunto' => 'Nova etapa no calendário!', 'cor' => '#10b981'],
        'update' => ['emoji' => '📝', 'titulo' => 'Etapa Atualizada', 'assunto' => 'Etapa atualizada no calendário', 'cor' => '#f59e0b'],
        'delete' => ['emoji' => '❌', 'titulo' => 'Etapa Removida', 'assunto' => 'Etapa removida do calendário', 'cor' => '#ef4444']
    ];

    $label = $actionLabels[$action] ?? $actionLabels['update'];

    // Formatar data da etapa
    $dataFormatada = '';
    if (!empty($etapaData['data'])) {
        $formatter = new \IntlDateFormatter('pt_BR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $dataFormatada = $formatter->format(new \DateTime($etapaData['data']));
    }

    $horaFormatada = !empty($etapaData['hora']) ? date('H:i', strtotime($etapaData['hora'])) : '';

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_USER, 'KartOps');

        // Usar BCC para enviar para todos os usuários com um único envio
        foreach ($usuarios as $u) {
            $mail->addBCC($u['email'], $u['nome']);
        }

        // Imagens para embed (CID)
        $sponsors = [
            'logo_camp' => 'images/logo-campeonato.png',
            'ainext' => 'images/logos/sponsors/ainext-logo.png',
            'novadutra' => 'images/logos/sponsors/novadutra.png',
            'cardoso' => 'images/logos/sponsors/cardoso-logo.png',
            'botequim' => 'images/logos/sponsors/botequimgp-logo.jpg',
            'autoradio' => 'images/logos/sponsors/autoradio-logo.png',
            'green' => 'images/logos/sponsors/greensolutions-logo.png',
            'kda' => 'images/logos/sponsors/logo-kda-raceweare-monocromatica.png'
        ];

        foreach ($sponsors as $cid => $path) {
            $fullPath = $baseDir . $path;
            if (file_exists($fullPath)) {
                $mail->addEmbeddedImage($fullPath, $cid, basename($path));
            }
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "🏎️ {$label['assunto']} — " . ($etapaData['nome'] ?? 'Etapa');

        $cor = $label['cor'];
        $etapaNome = htmlspecialchars($etapaData['nome'] ?? '');
        $kartodromo = htmlspecialchars($etapaData['kartodromo'] ?? '');
        $tipoEtapa = htmlspecialchars($etapaData['tipo_etapa'] ?? '');

        $detalhesHtml = "
            <table cellpadding='8' cellspacing='0' border='0' style='width: 100%; border-collapse: collapse;'>
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='font-weight: bold; color: #555; width: 120px;'>📌 Etapa</td>
                    <td style='color: #333;'>{$etapaNome}</td>
                </tr>
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='font-weight: bold; color: #555;'>🏁 Tipo</td>
                    <td style='color: #333;'>{$tipoEtapa}</td>
                </tr>
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='font-weight: bold; color: #555;'>📅 Data</td>
                    <td style='color: #333;'>{$dataFormatada}</td>
                </tr>
                <tr style='border-bottom: 1px solid #eee;'>
                    <td style='font-weight: bold; color: #555;'>🕐 Horário</td>
                    <td style='color: #333;'>{$horaFormatada}</td>
                </tr>
                <tr>
                    <td style='font-weight: bold; color: #555;'>📍 Local</td>
                    <td style='color: #333;'>{$kartodromo}</td>
                </tr>
            </table>
        ";

        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, {$cor}, {$cor}cc); padding: 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 8px 0 0; font-size: 14px; opacity: 0.9; }
                .content { padding: 40px 30px; color: #333333; line-height: 1.8; }
                .details-box { background: #f8f9fa; border-radius: 10px; padding: 20px; margin: 20px 0; border: 1px solid #e9ecef; }
                .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, {$cor}, {$cor}cc); color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$label['emoji']} {$label['titulo']}</h1>
                    <p>KartOps — Calendário de Etapas</p>
                </div>
                <div class='content'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <img src='cid:logo_camp' alt='Campeonato' style='max-width: 200px; height: auto;'>
                    </div>
                    <p>Uma alteração foi feita no calendário do campeonato por <strong>{$adminNome}</strong>:</p>

                    <div class='details-box'>
                        {$detalhesHtml}
                    </div>

                    <p style='text-align: center;'>
                        <a href='{$loginUrl}' class='btn' style='color: #ffffff !important;'>Ver Calendário Completo</a>
                    </p>
                </div>
                <div style='background: #f0f0f5; padding: 30px 20px; text-align: center; border-top: 2px solid {$cor};'>
                    <p style='font-size: 15px; color: #555; margin: 0 0 8px;'><strong>🤝 Apoie nossos patrocinadores!</strong></p>
                    <table cellpadding='0' cellspacing='0' border='0' align='center' style='margin: 0 auto;'>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://ainext.com.br' target='_blank'><img src='cid:ainext' alt='AINEXT' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://novadutramultimarcas.com.br' target='_blank'><img src='cid:novadutra' alt='Nova Dutra Multimarcas' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><img src='cid:cardoso' alt='Cardoso Funilaria' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 12px;'><a href='https://www.botequimgpkart.com.br/' target='_blank'><img src='cid:botequim' alt='Botequim GP Kart' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://autoradiopodcast.com.br' target='_blank'><img src='cid:autoradio' alt='AutoRadio Podcast' style='height: 40px; max-width: 120px;'></a></td>
                            <td style='padding: 8px 12px;'><a href='https://gsar.com.br' target='_blank'><img src='cid:green' alt='Green Solutions' style='height: 40px; max-width: 120px;'></a></td>
                        </tr>
                        <tr>
                            <td colspan='3' style='padding: 8px 12px; text-align: center;'><img src='cid:kda' alt='KDA Racewear' style='height: 40px; max-width: 120px;'></td>
                        </tr>
                    </table>
                </div>
                <div class='footer'>
                    <p>Portal KartOps © {$year}</p>
                    <p style='margin-top: 5px;'>Você recebeu este email porque está cadastrado no KartOps.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $body;
        $mail->AltBody = "{$label['titulo']}: {$etapaNome} - {$dataFormatada} às {$horaFormatada} em {$kartodromo}. Acesse: {$loginUrl}";

        $mail->send();
        error_log("[KartOps] Notificação de etapa ({$action}) enviada para " . count($usuarios) . " usuários");
        return true;
    } catch (Exception $e) {
        error_log("[KartOps] Erro ao enviar notificação de etapa: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPasswordResetEmail($toEmail, $token)
{
    // Usar URL configurada no config.php
    $resetLink = APP_URL . "/forgot-password.php?step=reset&token=$token";

    $mail = new PHPMailer(true); // true habilita exceções

    try {
        // Configurações do Servidor
        // $mail->SMTPDebug = 2;                      // Habilita saída de debug verbosa (útil se falhar)
        $mail->isSMTP();                                            // Usar SMTP
        $mail->Host = SMTP_HOST;                              // Servidor SMTP do Gmail
        $mail->SMTPAuth = true;                                   // Habilitar autenticação
        $mail->Username = SMTP_USER;                              // Email
        $mail->Password = SMTP_PASS;                              // Senha de App
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Criptografia TLS
        $mail->Port = SMTP_PORT;                              // Porta TCP

        // Destinatários
        $mail->setFrom(SMTP_USER, 'KartOps');                      // Remetente (Gmail exige que seja o mesmo da conta autenticada)
        $mail->addAddress($toEmail);                                // Destinatário

        // Conteúdo
        $mail->isHTML(true);                                        // Email em HTML
        $mail->CharSet = 'UTF-8';                                   // Evitar problemas de acentuação
        $mail->Subject = 'Recuperação de Senha - KartOps';

        // Template do corpo do email
        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
                .content { padding: 40px 30px; color: #333333; line-height: 1.6; }
                .btn { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; text-align: center; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
                .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
                .link-alt { word-break: break-all; color: #667eea; font-size: 12px; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🏎️ KartOps</h1>
                </div>
                <div class='content'>
                    <h2>Olá!</h2>
                    <p>Recebemos uma solicitação para redefinir a senha da sua conta.</p>
                    <p style='text-align: center;'>
                        <a href='$resetLink' class='btn'>Redefinir Minha Senha</a>
                    </p>
                    <p>Se o botão não funcionar, copie e cole este link no seu navegador:</p>
                    <p class='link-alt'>$resetLink</p>
                    <p><em>Este link expira em 1 hora.</em></p>
                    <p>Se você não solicitou isso, ignore este e-mail.</p>
                </div>
                <div class='footer'>
                    Portal KartOps © " . date('Y') . "
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $body;
        $mail->AltBody = "Olá! Use este link para redefinir sua senha: $resetLink"; // Texto puro

        $mail->send();

        // Sucesso
        return [
            'sent' => true,
            'link' => null // Não exibir link na tela, pois foi enviado!
        ];

    } catch (Exception $e) {
        // Erro
        error_log("Erro no envio de email: {$mail->ErrorInfo}");

        // Em debug, retornar erro e link
        $msgErro = '';
        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            $msgErro = "Erro SMTP: " . $mail->ErrorInfo;
        }

        return [
            'sent' => false,
            'error' => $msgErro,
            'link' => $resetLink // Fallback: mostra link na tela se envio falhar
        ];
    }
}
?>