<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../config/config.php');

header("Content-Type: text/plain; charset=UTF-8");

echo "=== TESTE META EVENT ===\n\n";

// Testa a função metaEvent
echo "1. Testando metaEvent...\n";

try {
    $result = metaEvent('PageView', [], ['content_name' => 'Teste'], 'app');
    
    echo "2. Resultado:\n";
    print_r($result);
    
    echo "\n3. Verifique o arquivo logs/errors.txt para ver os logs detalhados\n";
    echo "4. Verifique o arquivo logs/meta_pixel_events.txt para ver o log do evento\n";
    
} catch (\Throwable $e) {
    echo "ERRO CAPTURADO:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
