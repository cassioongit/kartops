<?php
/**
 * =====================================================
 * TERMOS DE SERVIÇO - KartOps
 * =====================================================
 */

$pageTitle = 'Termos de Serviço';
$additionalCSS = [];

require_once 'includes/header.php';
?>

<div class="container"
    style="max-width: 800px; margin: 120px auto 40px; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <h1 style="color: #1a1a1a; margin-bottom: 20px; font-size: 28px; text-align: center;">Termos de Serviço</h1>

    <div style="color: #4a5568; line-height: 1.6; font-size: 15px;">
        <p style="margin-bottom: 25px;"><strong>Última atualização:</strong>
            <?= date('d/m/Y') ?>
        </p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">1. Aceitação dos Termos</h2>
        <p style="margin-bottom: 15px;">Ao acessar e utilizar a plataforma <strong>KartOps</strong>, você concorda em
            cumprir e sujeitar-se a estes Termos de Serviço, bem como a nossa Política de Privacidade. Caso não concorde
            com qualquer parte destes termos, você não deverá utilizar nosso sistema.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">2. Descrição do Serviço</h2>
        <p style="margin-bottom: 15px;">O KartOps é uma plataforma digital destinada à gestão, acompanhamento,
            classificação, visualização de vídeos (onboards), resultados e organização de campeonatos de kart.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">3. Cadastro de Usuários</h2>
        <p style="margin-bottom: 15px;">Para utilizar certas funcionalidades do sistema, torna-se essencial a criação de
            uma conta. O usuário se compromete a:</p>
        <ul style="margin-bottom: 15px; padding-left: 20px;">
            <li style="margin-bottom: 8px;">Fornecer informações verdadeiras, exatas, atuais e completas.</li>
            <li style="margin-bottom: 8px;">Manter e atualizar prontamente suas informações de registro.</li>
            <li style="margin-bottom: 8px;">Zelar pela segurança de sua senha e identificação de conta (login).</li>
            <li style="margin-bottom: 8px;">Notificar imediatamente os administradores sobre qualquer uso não autorizado
                da sua conta ou qualquer outra quebra de segurança.</li>
        </ul>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">4. Regras de Conduta</h2>
        <p style="margin-bottom: 15px;">Ao utilizar o sistema, o usuário se compromete a <strong>NÃO</strong>:</p>
        <ul style="margin-bottom: 15px; padding-left: 20px;">
            <li style="margin-bottom: 8px;">Utilizar a plataforma para fins ilícitos, ou promover atividades ilegais.
            </li>
            <li style="margin-bottom: 8px;">Tentar obter acesso não autorizado aos sistemas, a outras contas, sistemas
                de computador, ou redes conectadas aos servidores de forma ilícita, através da extração de senhas ou
                qualquer outro meio.</li>
            <li style="margin-bottom: 8px;">Fazer upload de imagens, links e avatares contendo material ofensivo,
                difamatório ou adulto.</li>
        </ul>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">5. Propriedade Intelectual</h2>
        <p style="margin-bottom: 15px;">Todo o conteúdo da plataforma, incluindo, sem limitação, textos, gráficos,
            imagens, logotipos, ícones, fotografias, conteúdo editorial, notificações, softwares e qualquer outro
            material, pertencem à administração e estão protegidos pela lei de direitos autorais portuguesa e
            internacional e outras leis de propriedade intelectual.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">6. Limitação de Responsabilidade</h2>
        <p style="margin-bottom: 15px;">A administração do KartOps não será responsável por quaisquer danos diretos,
            indiretos, incidentais, especiais, consequentes ou punitivos decorrentes do uso de ou incapacidade de usar o
            sistema. Embora nós nos esforcemos para manter o sistema online, não garantimos que a plataforma estará
            sempre ininterrupta ou livre de erros.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">7. Modificações no Serviço</h2>
        <p style="margin-bottom: 15px;">Reservamo-nos o direito de, a qualquer momento, modificar ou descontinuar,
            temporariamente ou permanentemente, o sistema (ou qualquer parte dele) com ou sem aviso prévio. Nós não
            seremos responsáveis perante o usuário ou quaisquer terceiros por qualquer modificação, suspensão ou
            descontinuação do serviço.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">8. Integração com Google</h2>
        <p style="margin-bottom: 15px;">O KartOps permite o acesso através da sua conta Google. Esta funcionalidade visa
            facilitar o seu registro e acesso, utilizando informações já verificadas. Ao utilizar este método, você
            concorda com o compartilhamento do seu perfil básico conosco, conforme detalhado em nossa Política de
            Privacidade.</p>

        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">9. Contato</h2>
        <p style="margin-bottom: 15px;">Para esclarecimentos quanto às disposições destes Termos de Serviço, o usuário
            deverá recorrer aos organizadores e administradores responsáveis pelo campeonato através dos meios oficiais.
        </p>
        <h2 style="color: #2d3748; font-size: 20px; margin: 30px 0 15px;">Google OAuth - Uso de Dados</h2>
        <p style="margin-bottom: 15px;">Em conformidade com a Política de Dados do Usuário dos Serviços de API do
            Google, o KartOps declara que:</p>
        <ul style="margin-bottom: 15px; padding-left: 20px;">
            <li style="margin-bottom: 8px;"><strong>O que coletamos:</strong> Nome, endereço de e-mail e URL da foto de
                perfil.</li>
            <li><strong>Finalidade:</strong> Identificar o piloto no ranking oficial e permitir acesso seguro ao
                sistema.</li>
            <li><strong>Compartilhamento:</strong> Não compartilhamos seus dados provenientes do Google com nenhum
                terceiro.</li>
        </ul>
        <p style="margin-bottom: 15px; font-style: italic;">Nosso uso de informações recebidas das APIs do Google
            aderirá à Política de Dados do Usuário dos Serviços de API do Google, incluindo os requisitos de Uso
            Limitado.</p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>