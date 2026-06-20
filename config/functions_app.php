<?php
// Evita inclusão duplicada
if (function_exists('converterDiaSemana')) {
    return;
}

function converterDiaSemana($dia) {
    $dias_semana_br = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab');
    $dias_semana_en = array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

    $index = array_search($dia, $dias_semana_br);

    if ($index !== false) {
        return $dias_semana_en[$index];
    }
}

// Função de comparação para ordenar por disponibilidade ASC
function compararDisponibilidade($a, $b) {
    return strcmp($a['disponibilidade'], $b['disponibilidade']);
}

function ClearSearch($pesquisa){
    $palavras = array("da","de","di","do","du","para","pra","em","in","por","até","ate");
    return preg_replace('/\b('.implode('|',$palavras).')\b/','',$pesquisa);
}

function diaSemana($dataAtual)
{
    // Obtém o dia da semana em inglês
    $diaDaSemanaEmIngles = date('D', strtotime($dataAtual));

    // Mapeia os nomes em inglês para os nomes em português
    $traducaoDiasDaSemana = array(
        'Mon' => 'seg',
        'Tue' => 'ter',
        'Wed' => 'qua',
        'Thu' => 'qui',
        'Fri' => 'sex',
        'Sat' => 'sab',
        'Sun' => 'dom'
    );
    
    // Obtém o dia da semana em português usando o array de tradução
    return $traducaoDiasDaSemana[$diaDaSemanaEmIngles];
}

function todosPreenchidos($dados) {
    foreach ($dados as $key => $value) {
        if (empty($value)) {
            return false;
        }
    }
    return true;
}

function gerarCodigoPedido($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function limparCPF($cpf) {
    $cpfLimpo = preg_replace('/\D/', '', $cpf);
    return $cpfLimpo;
}

/**
 * Evita reserva duplicada por cliques repetidos no app (mesmos dados + Pendente).
 */
function reserva_existe_duplicada_pendente(PDO $db, array $post): ?array
{
    $campos = ['id_suite', 'chegada_reserva', 'periodo_reserva', 'valor_reserva', 'id_usuario'];
    foreach ($campos as $campo) {
        if (!isset($post[$campo]) || $post[$campo] === '' || $post[$campo] === null) {
            return null;
        }
    }

    $stmt = $db->prepare(
        'SELECT * FROM reservas
         WHERE id_suite = ?
           AND chegada_reserva = ?
           AND periodo_reserva = ?
           AND valor_reserva = ?
           AND id_usuario = ?
           AND status_reserva = ?
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        $post['id_suite'],
        $post['chegada_reserva'],
        $post['periodo_reserva'],
        $post['valor_reserva'],
        $post['id_usuario'],
        'Pendente',
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function traduzirStatusPagamento($status) {
    $statusTraduzido = [
        'approved' => 'Aprovado',
        'pending' => 'Pendente',
        'in_process' => 'Em Processo',
        'rejected' => 'Rejeitado',
        'cancelled' => 'Cancelado',
        'refunded' => 'Reembolsado',
        'in_mediation' => 'Em Mediação',
        'charged_back' => 'Estornado',
        'Pendente' => 'Aprovação Pendente',
        'Aceito' => 'Aguardando Pagamento',
        'Recusado' => 'Reserva Recusada',
        'Cancelado' => 'Reserva Cancelada',
    ];

    return $statusTraduzido[$status] ?? 'Status desconhecido';
}

function corStatusPagamento($status) {
    $classeBadge = [
        'approved' => 'success',
        'pending' => 'warning',
        'in_process' => 'info',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'refunded' => 'primary',
        'in_mediation' => 'info',
        'charged_back' => 'dark',
        'Pendente' => 'warning',
        'Aceito' => 'info',
        'Recusado' => 'danger',
        'Cancelado' => 'danger',
    ];

    return $classeBadge[$status] ?? 'dark';
}

// ============================================================================
// META PIXEL - INTEGRAÇÃO COM SDK OFICIAL DO FACEBOOK
// ============================================================================

// Use statements seguindo a documentação oficial do SDK
// https://github.com/facebook/facebook-php-business-sdk
use FacebookAds\Api;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\EventResponse;
use FacebookAds\Object\ServerSide\UserData;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\ActionSource;

/**
 * Envia evento ao Meta Pixel usando o SDK oficial do Facebook
 * Seguindo o padrão da documentação: https://github.com/facebook/facebook-php-business-sdk
 * 
 * @param string $eventName Nome do evento (ViewContent, Purchase, InitiateCheckout, PageView)
 * @param array $userData Dados do usuário ['email', 'phone', 'first_name', 'last_name']
 * @param array $customData Dados customizados ['content_name', 'content_ids', 'value', 'currency', etc.]
 * @param string $actionSource Fonte da ação ('app', 'website', 'phone_call', etc.)
 * @return array Resposta da API do Meta
 */
function metaEvent(string $eventName, array $userData = [], array $customData = [], string $actionSource = 'website') {
    
    // Validações iniciais
    if (!defined('META_PIXEL_ID') || !defined('META_PIXEL_TOKEN')) {
        $error = 'ERRO: Configuração não encontrada';
        logMetaEvent($eventName, false, $error, $customData);
        return ['error' => 'Configuração do Meta Pixel não encontrada'];
    }

    $PIXEL_ID = trim(META_PIXEL_ID);
    $TOKEN    = trim(META_PIXEL_TOKEN);
    
    if (empty($TOKEN) || empty($PIXEL_ID)) {
        $error = 'ERRO: Token ou Pixel ID vazios';
        logMetaEvent($eventName, false, $error, $customData);
        return ['error' => 'Token ou Pixel ID não configurados'];
    }

    // Verifica se o SDK está disponível
    $vendorExists = file_exists(__DIR__ . '/../vendor/autoload.php');
    $sdkPath = __DIR__ . '/../vendor/facebook/php-business-sdk';
    $sdkExists = is_dir($sdkPath);
    
    // Tenta carregar o autoload se ainda não foi carregado
    if ($vendorExists && !class_exists('FacebookAds\Api')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    
    $apiClassExists = class_exists('FacebookAds\Api');
    
    if (!$apiClassExists) {
        $error = 'ERRO: SDK do Facebook não está instalado';
        logMetaEvent($eventName, false, $error . '. Execute: composer install no servidor', $customData);
        return ['error' => 'SDK do Facebook não instalado'];
    }

    try {
        // Inicializa a API seguindo a documentação oficial
        Api::init(null, null, $TOKEN);
        $api = Api::instance();
        
        // Prepara dados do usuário
        $userDataObj = new UserData();
        
        if (!empty($userData['email'])) {
            $userDataObj->setEmail($userData['email']);
        }
        if (!empty($userData['phone'])) {
            $userDataObj->setPhone($userData['phone']);
        }
        if (!empty($userData['first_name'])) {
            $userDataObj->setFirstName($userData['first_name']);
        }
        if (!empty($userData['last_name'])) {
            $userDataObj->setLastName($userData['last_name']);
        }
        
        // IP e User Agent (obrigatórios)
        $userDataObj->setClientIpAddress($_SERVER['REMOTE_ADDR'] ?? '');
        $userDataObj->setClientUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Prepara dados customizados
        $customDataObj = new CustomData();
        
        // Trata o valor: se for null ou undefined, define como 0.00
        // Para eventos Purchase, o valor é obrigatório
        if (isset($customData['value']) && $customData['value'] !== null) {
            $value = (float)$customData['value'];
            // Garante que o valor seja válido (não NaN, não infinito)
            if (is_nan($value) || is_infinite($value)) {
                $value = 0.00;
            }
            $customDataObj->setValue($value);
        } else {
            // Se o valor não estiver definido ou for null/undefined, define como 0.00
            $customDataObj->setValue(0.00);
        }
        if (isset($customData['currency'])) {
            $customDataObj->setCurrency($customData['currency']);
        }
        if (isset($customData['content_name'])) {
            $customDataObj->setContentName($customData['content_name']);
        }
        if (isset($customData['content_ids']) && is_array($customData['content_ids'])) {
            $customDataObj->setContentIds($customData['content_ids']);
        }
        if (isset($customData['content_type'])) {
            $customDataObj->setContentType($customData['content_type']);
        }
        
        // ✅ Se for app, adiciona identificadores customizados (forma correta de identificar app)
        $isApp = ($actionSource === 'app');
        if ($isApp) {
            // Adiciona identificadores de app via custom_properties
            $customProperties = [];
            if (!isset($customData['platform'])) {
                $customProperties['platform'] = 'app';
            } else {
                $customProperties['platform'] = $customData['platform'];
            }
            if (!isset($customData['app_name'])) {
                $customProperties['app_name'] = 'SwingMotel';
            } else {
                $customProperties['app_name'] = $customData['app_name'];
            }
            if (!isset($customData['app_version'])) {
                $customProperties['app_version'] = '1.0.0';
            } else {
                $customProperties['app_version'] = $customData['app_version'];
            }
            
            // Adiciona outros campos customizados se existirem
            foreach ($customData as $key => $value) {
                if (!in_array($key, ['value', 'currency', 'content_name', 'content_ids', 'content_type', 'platform', 'app_name', 'app_version', 'advertiser_tracking_enabled'])) {
                    $customProperties[$key] = $value;
                }
            }
            
            if (!empty($customProperties)) {
                $customDataObj->setCustomProperties($customProperties);
            }
        }

        // Cria o evento seguindo o padrão da documentação
        $event = new Event();
        $event->setEventName($eventName);
        $event->setEventTime(time());
        $event->setEventId(uniqid('evt_', true));
        $event->setUserData($userDataObj);
        $event->setCustomData($customDataObj);
        
        // ✅ FORMA CORRETA: Sempre usa action_source='website' e identifica app via custom_data
        // Isso evita problemas com app_data.advertiser_tracking_enabled no SDK 16.0
        // O Meta aceita isso perfeitamente e aparece nos relatórios personalizados
        
        // Se action_source='app', muda para 'website' e identifica via custom_data
        if ($isApp) {
            // Sempre usa website para app (forma recomendada pelo Meta)
            $actionSourceValue = ActionSource::WEBSITE;
            
            // Adiciona event_source_url fake para identificar como app (opcional)
            $event->setEventSourceUrl('app://swingmotel');
        } else {
            // Converte outros action_source para constantes
            if ($actionSource === 'website') {
                $actionSourceValue = ActionSource::WEBSITE;
            } elseif ($actionSource === 'phone_call') {
                $actionSourceValue = ActionSource::PHONE_CALL;
            } elseif ($actionSource === 'email') {
                $actionSourceValue = ActionSource::EMAIL;
            } elseif ($actionSource === 'chat') {
                $actionSourceValue = ActionSource::CHAT;
            } elseif ($actionSource === 'physical_store') {
                $actionSourceValue = ActionSource::PHYSICAL_STORE;
            } elseif ($actionSource === 'system_generated') {
                $actionSourceValue = ActionSource::SYSTEM_GENERATED;
            } elseif ($actionSource === 'business_messaging') {
                $actionSourceValue = ActionSource::BUSINESS_MESSAGING;
            } else {
                $actionSourceValue = ActionSource::OTHER;
            }
        }
        
        $event->setActionSource($actionSourceValue);

        // Envia o evento seguindo o padrão da documentação
        $events = [$event];
        $request = new EventRequest($PIXEL_ID);
        $request->setEvents($events);
        
        $responseObj = $request->execute();
        
        // Converte resposta do objeto EventResponse para array (SDK 16.0)
        $response = [
            'events_received' => $responseObj->getEventsReceived(),
            'messages' => $responseObj->getMessages(),
            'fbtrace_id' => $responseObj->getFbTraceId()
        ];
        
        // Verifica sucesso
        $success = isset($response['events_received']) && $response['events_received'] > 0;
        $eventId = $event->getEventId();
        
        if ($success) {
            logMetaEvent($eventName, true, 'Evento entregue com sucesso via SDK', $customData, $eventId, 200, $response);
        } else {
            logMetaEvent($eventName, false, 'Evento não foi recebido pelo Meta', $customData, $eventId, 200, $response);
        }
        
        return $response;
        
    } catch (\Exception $e) {
        $errorMsg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $errorCode = $e->getCode();
        
        // Tenta obter mais detalhes do erro se for RequestException
        $errorDetails = [];
        $fullErrorMsg = 'Erro no SDK: ' . $errorMsg;
        
        if ($e instanceof \FacebookAds\Http\Exception\RequestException) {
            try {
                $response = $e->getResponse();
                if ($response !== null) {
                    $errorDetails['http_status'] = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
                    $errorDetails['response_content'] = method_exists($response, 'getContent') ? $response->getContent() : null;
                }
                
                // Obtém detalhes específicos do erro do Facebook
                $reflection = new \ReflectionClass($e);
                if ($reflection->hasProperty('errorUserMessage')) {
                    $prop = $reflection->getProperty('errorUserMessage');
                    $prop->setAccessible(true);
                    $errorDetails['error_user_message'] = $prop->getValue($e);
                    if ($errorDetails['error_user_message']) {
                        $fullErrorMsg .= ' | ' . $errorDetails['error_user_message'];
                    }
                }
            } catch (\Throwable $ex) {
                // Ignora erros ao tentar obter detalhes
            }
        }
        
        logMetaEvent($eventName, false, $fullErrorMsg, $customData, '', $errorDetails['http_status'] ?? 0);
        
        return [
            'error' => $errorMsg,
            'code' => $errorCode,
            'file' => $errorFile,
            'line' => $errorLine
        ];
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        
        logMetaEvent($eventName, false, 'Erro fatal no SDK: ' . $errorMsg . ' | Arquivo: ' . $errorFile . ' | Linha: ' . $errorLine, $customData, '', 0);
        
        return [
            'error' => $errorMsg,
            'code' => $e->getCode(),
            'file' => $errorFile,
            'line' => $errorLine
        ];
    }
}

/**
 * Função de log para eventos do Meta Pixel
 */
function logMetaEvent(
    string $eventName, 
    bool $success, 
    string $message, 
    array $customData = [], 
    string $eventId = '', 
    int $httpCode = 0,
    array $responseData = []
) {
    $logDir = __DIR__ . '/../logs';
    
    // Cria o diretório de logs se não existir
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/meta_pixel_events.txt';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCESSO' : 'FALHA';
    
    // Prepara dados para log (sem informações sensíveis)
    $logData = [
        'event_name' => $eventName,
        'event_id' => $eventId,
        'content_name' => $customData['content_name'] ?? 'N/A',
        'content_ids' => $customData['content_ids'] ?? [],
        'value' => $customData['value'] ?? null,
        'currency' => $customData['currency'] ?? null
    ];
    
    // Prepara informações adicionais para erros
    $additionalInfo = '';
    if (!$success && isset($responseData['error'])) {
        $error = $responseData['error'];
        $additionalInfo = sprintf(
            ' | Erro Code: %s | Erro Type: %s',
            $error['code'] ?? 'N/A',
            $error['type'] ?? 'N/A'
        );
    }
    
    $logEntry = sprintf(
        "[%s] %s | Evento: %s | ID: %s | HTTP: %d | Mensagem: %s%s | Dados: %s | Resposta: %s\n",
        $timestamp,
        $status,
        $eventName,
        $eventId ?: 'N/A',
        $httpCode,
        $message,
        $additionalInfo,
        json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    
    // Adiciona separador visual
    $logEntry .= str_repeat('-', 120) . "\n";
    
    // Escreve no arquivo de log
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/** Prazo em minutos para expiração de pagamento de reserva (cron + Mercado Pago). */
function reserva_pagamento_expiracao_minutos(): int
{
    return 30;
}

/**
 * ISO 8601 para date_of_expiration do Mercado Pago (Checkout Pro, PIX, Orders).
 *
 * @see https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-settings/expiration-date
 */
function mp_reserva_date_of_expiration(?int $minutos = null, $from = null): string
{
    $minutos = $minutos ?? reserva_pagamento_expiracao_minutos();
    $tz = new DateTimeZone('America/Sao_Paulo');
    if ($from instanceof DateTimeInterface) {
        $base = DateTimeImmutable::createFromInterface($from)->setTimezone($tz);
    } else {
        $base = new DateTimeImmutable('now', $tz);
    }
    return $base->modify('+' . (int) $minutos . ' minutes')->format('Y-m-d\TH:i:s.000P');
}