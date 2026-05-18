<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RawPrinterService
{
    /**
     * Enviar comando RAW a impresora de red
     */
    public function sendToNetworkPrinter($ip, $port, $data)
    {
        try {
            if (empty($ip)) {
                throw new \Exception("IP de impresora no configurada");
            }

            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if ($socket === false) {
                throw new \Exception("No se pudo crear socket: " . socket_strerror(socket_last_error()));
            }
            
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 5, 'usec' => 0));
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 5, 'usec' => 0));
            
            $result = @socket_connect($socket, $ip, (int)$port);
            
            if ($result === false) {
                $error = socket_strerror(socket_last_error($socket));
                socket_close($socket);
                throw new \Exception("No se pudo conectar a la impresora en $ip:$port - $error");
            }
            
            $bytesWritten = @socket_write($socket, $data, strlen($data));
            
            if ($bytesWritten === false) {
                $error = socket_strerror(socket_last_error($socket));
                socket_close($socket);
                throw new \Exception("Error al enviar datos a la impresora: $error");
            }
            
            socket_close($socket);
            
            Log::info("Impresión enviada a $ip:$port - Bytes: $bytesWritten");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error en impresión RAW: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generar bytes ESC/POS y devolver base64
     */
    public function generateTicketData($orderData, $width = 80, $ticketTitle = 'MI NEGOCIO', $businessInfo = [])
    {
        return base64_encode($this->generateTicket($orderData, $width, $ticketTitle, $businessInfo));
    }

    /**
     * Generar ticket según ancho de impresión
     */
    public function generateTicket($orderData, $width = 80, $ticketTitle = 'MI NEGOCIO', $businessInfo = [])
    {
        if ($width === 50 || $width === '50mm') {
            return $this->generate50mmTicket($orderData, $ticketTitle, $businessInfo);
        }
        return $this->generate80mmTicket($orderData, $ticketTitle, $businessInfo);
    }

    /**
     * Generar ticket para impresoras de 80mm (48 caracteres)
     */
    private function generate80mmTicket($orderData, $ticketTitle, $businessInfo)
    {
        $lineWidth = 48;
        $date = date('d/m/Y H:i');
        $userName = $orderData['user_name'] ?? 'Caja';
        $orderId = $orderData['order_id'] ?? 1;

        $ticket = chr(27) . chr(64); // Initialize printer (ESC @)

        // Título centrado (double width - same as items)
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        $ticket .= str_pad($ticketTitle, $lineWidth, " ", STR_PAD_BOTH) . "\n";
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        // Información del negocio
        if (!empty($businessInfo['address'])) {
            $ticket .= str_pad($businessInfo['address'], $lineWidth, " ", STR_PAD_BOTH) . "\n";
        }
        if (!empty($businessInfo['phone'])) {
            $ticket .= str_pad("Tel: " . $businessInfo['phone'], $lineWidth, " ", STR_PAD_BOTH) . "\n";
        }
        if (!empty($businessInfo['nit'])) {
            $ticket .= str_pad("NIT: " . $businessInfo['nit'], $lineWidth, " ", STR_PAD_BOTH) . "\n";
        }

        $ticket .= str_repeat("-", $lineWidth) . "\n";
        $ticket .= "Pedido: #$orderId | Cajero: $userName\n";
        $ticket .= "$date\n";
        $ticket .= str_repeat("-", $lineWidth) . "\n";

        // Items (double width for better readability)
        $cart = $orderData['cart'] ?? [];
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        foreach ($cart as $item) {
            $qty = $item['qty'] ?? 1;
            $name = $item['name'] ?? 'Producto';
            $amount = $item['amount'] ?? 0;

            for ($i = 0; $i < $qty; $i++) {
                $productText = "[ ] " . substr($name, 0, 35);
                $priceText = "$" . number_format($amount, 2);
                $formattedLine = str_pad($productText, $lineWidth - strlen($priceText), " ");
                $formattedLine .= $priceText;
                $ticket .= $formattedLine . "\n";
            }
        }
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        $ticket .= str_repeat("-", $lineWidth) . "\n";

        // Total (double width)
        $total = $orderData['total'] ?? 0;
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        $ticket .= "TOTAL:" . str_pad("$" . number_format($total, 2), $lineWidth - 6, " ", STR_PAD_LEFT) . "\n";
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        $ticket .= str_repeat("=", $lineWidth) . "\n";

        // Feed paper and partial cut
        $ticket .= chr(27) . chr(100) . chr(3); // ESC d 3: feed 3 lines
        $ticket .= chr(29) . chr(86) . chr(1); // GS V 1: partial cut
        $ticket .= str_repeat("\n", 2);

        return $ticket;
    }

    /**
     * Generar ticket para impresoras de 50mm (32 caracteres)
     */
    private function generate50mmTicket($orderData, $ticketTitle, $businessInfo)
    {
        $lineWidth = 32;
        $date = date('d/m/Y H:i');
        $userName = $orderData['user_name'] ?? 'Caja';
        $orderId = $orderData['order_id'] ?? 1;

        $ticket = chr(27) . chr(64); // Initialize printer (ESC @)

        // Título centrado (double width - same as items)
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        $ticket .= str_pad($ticketTitle, $lineWidth, " ", STR_PAD_BOTH) . "\n";
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        // Información reducida
        if (!empty($businessInfo['nit'])) {
            $ticket .= "NIT: " . $businessInfo['nit'] . "\n";
        }

        $ticket .= str_repeat("-", $lineWidth) . "\n";
        $ticket .= "Pedido: #$orderId | Cajero: $userName\n";
        $ticket .= "$date\n";
        $ticket .= str_repeat("-", $lineWidth) . "\n";

        // Items (double width for better readability)
        $cart = $orderData['cart'] ?? [];
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        foreach ($cart as $item) {
            $qty = $item['qty'] ?? 1;
            $name = substr($item['name'] ?? 'Prod', 0, 20);
            $amount = $item['amount'] ?? 0;

            for ($i = 0; $i < $qty; $i++) {
                $productText = "[ ] " . $name;
                $priceText = "$" . number_format($amount, 2);
                $formattedLine = str_pad($productText, $lineWidth - strlen($priceText), " ");
                $formattedLine .= $priceText;
                $ticket .= $formattedLine . "\n";
            }
        }
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        $ticket .= str_repeat("-", $lineWidth) . "\n";

        // Total (double width)
        $total = $orderData['total'] ?? 0;
        $ticket .= chr(27) . chr(33) . chr(16); // ESC ! 16: double width
        $ticket .= "TOTAL:" . str_pad("$" . number_format($total, 2), $lineWidth - 6, " ", STR_PAD_LEFT) . "\n";
        $ticket .= chr(27) . chr(33) . chr(0); // ESC ! 0: normal size

        $ticket .= str_repeat("=", $lineWidth) . "\n";

        // Feed paper and partial cut
        $ticket .= chr(27) . chr(100) . chr(3); // ESC d 3: feed 3 lines
        $ticket .= chr(29) . chr(86) . chr(1); // GS V 1: partial cut
        $ticket .= str_repeat("\n", 2);

        return $ticket;
    }

    /**
     * Imprimir ticket
     */
    public function printTicket($orderData, $printerConfig)
    {
        if (empty($printerConfig['ip'])) {
            throw new \Exception("Impresora no configurada");
        }

        $width = (int) ($printerConfig['width'] ?? 80);
        $title = $printerConfig['title'] ?? 'MI NEGOCIO';
        $businessInfo = $printerConfig['business'] ?? [];

        $ticketData = $this->generateTicket($orderData, $width, $title, $businessInfo);

        return $this->sendToNetworkPrinter(
            $printerConfig['ip'],
            $printerConfig['port'] ?? 9100,
            $ticketData
        );
    }
}